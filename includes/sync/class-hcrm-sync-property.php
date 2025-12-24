<?php
/**
 * Property Sync class for handling property synchronization.
 *
 * @package HCRM_Houzez
 * @since   1.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Class HCRM_Sync_Property
 *
 * Handles the synchronization of property data between WordPress and CRM.
 *
 * @since 1.0.0
 */
class HCRM_Sync_Property {

    /**
     * Data mapper instance.
     *
     * @var HCRM_Data_Mapper
     */
    private $mapper;

    /**
     * Constructor.
     *
     * @param HCRM_Data_Mapper|null $mapper Data mapper instance.
     */
    public function __construct($mapper = null) {
        $this->mapper = $mapper ?? new HCRM_Data_Mapper();
    }

    /**
     * Prepare property data for API submission.
     *
     * @param int $property_id WordPress property ID.
     * @return array API-ready property data.
     */
    public function prepare_for_api($property_id) {
        $post = get_post($property_id);
        if (!$post || $post->post_type !== 'property') {
            return [];
        }

        // Get property meta
        $meta = $this->get_property_meta($property_id);

        // Get property taxonomies
        $taxonomies = $this->get_property_taxonomies($property_id);

        // Map to CRM format
        $data = $this->mapper->houzez_to_crm([
            'meta'       => $meta,
            'taxonomies' => $taxonomies,
        ]);

        // Add post data
        $data['title'] = $post->post_title;
        $data['slug'] = $post->post_name;
        $data['listing_id'] = 'wp-' . $property_id;
        $data['published_at'] = $post->post_date;
        $data['listing_status'] = $this->map_post_status($post->post_status);

        // Add description
        if (!isset($data['detail'])) {
            $data['detail'] = [];
        }
        $data['detail']['description'] = $post->post_content;

        // Add images
        $images = $this->get_property_images($property_id);
        if (!empty($images)) {
            $data['images'] = $images;
        }

        // Add floor plans
        $floor_plans = $this->get_floor_plans($property_id);
        if (!empty($floor_plans)) {
            $data['floor_plans'] = $floor_plans;
        }

        // Add agent/agency data
        $agent_data = $this->get_property_agent_data($property_id);
        if (!empty($agent_data['assignees'])) {
            $data['assignees'] = $agent_data['assignees'];
        }
        if (!empty($agent_data['agency'])) {
            $data['agency'] = $agent_data['agency'];
        }

        // Add video URL if exists
        $video_url = get_post_meta($property_id, 'fave_video_url', true);
        if (!empty($video_url)) {
            if (!isset($data['custom_fields'])) {
                $data['custom_fields'] = [];
            }
            $data['custom_fields'][] = ['key' => 'video_url', 'value' => $video_url];
        }

        // Add virtual tour if exists
        $virtual_tour = get_post_meta($property_id, 'fave_virtual_tour', true);
        if (!empty($virtual_tour)) {
            if (!isset($data['custom_fields'])) {
                $data['custom_fields'] = [];
            }
            $data['custom_fields'][] = ['key' => 'virtual_tour', 'value' => $virtual_tour];
        }

        // Add owner_contact and created_by from post author
        $author = get_user_by('ID', $post->post_author);
        if ($author) {
            $name_parts = explode(' ', $author->display_name, 2);
            $author_uuid = $this->get_user_crm_uuid($author->ID);

            // Owner contact
            $data['owner_contact'] = [
                'uuid'       => $author_uuid,
                'first_name' => $name_parts[0] ?? '',
                'last_name'  => $name_parts[1] ?? '',
                'email'      => $author->user_email,
                'phone'      => get_user_meta($author->ID, 'phone', true) ?: '',
            ];

            // Created by user
            $data['created_by'] = [
                'uuid'  => $author_uuid,
                'name'  => $author->display_name,
                'email' => $author->user_email,
                'role'  => 'agent',
            ];
        }

        return $data;
    }

    /**
     * Map WordPress post_status to CRM listing_status.
     *
     * @param string $post_status WordPress post status.
     * @return string CRM listing status.
     */
    public function map_post_status($post_status) {
        $status_map = [
            'draft'   => 'draft',
            'pending' => 'pending',
            'publish' => 'published',
            'expired' => 'expired',
            'trash'   => 'trashed',
        ];

        return $status_map[$post_status] ?? 'draft';
    }

    /**
     * Get all property meta fields.
     *
     * @param int $property_id Property ID.
     * @return array Meta data.
     */
    public function get_property_meta($property_id) {
        $meta = [];
        $supported_fields = $this->mapper->get_supported_meta_fields();

        foreach ($supported_fields as $field) {
            $value = get_post_meta($property_id, $field, true);
            if ($value !== '' && $value !== false) {
                $meta[$field] = $value;
            }
        }

        // Add additional fields not in mapper
        $additional_fields = [
            'fave_property_location',
            'fave_video_url',
            'fave_virtual_tour',
            'fave_agent_display_option',
            'fave_agents',
            'fave_property_agency',
        ];

        foreach ($additional_fields as $field) {
            $value = get_post_meta($property_id, $field, true);
            if ($value !== '' && $value !== false) {
                $meta[$field] = $value;
            }
        }

        return $meta;
    }

    /**
     * Get property taxonomies.
     *
     * @param int $property_id Property ID.
     * @return array Taxonomy terms.
     */
    public function get_property_taxonomies($property_id) {
        $taxonomies = [];
        $supported_taxonomies = $this->mapper->get_supported_taxonomies();

        foreach ($supported_taxonomies as $taxonomy) {
            $terms = wp_get_post_terms($property_id, $taxonomy, ['fields' => 'all']);
            if (!is_wp_error($terms) && !empty($terms)) {
                $taxonomies[$taxonomy] = $terms;
            }
        }

        return $taxonomies;
    }

    /**
     * Get property images formatted for API.
     *
     * @param int $property_id Property ID.
     * @return array Images data.
     */
    public function get_property_images($property_id) {
        $images = [];

        // Get featured image
        $featured_id = get_post_thumbnail_id($property_id);
        if ($featured_id) {
            $url = wp_get_attachment_url($featured_id);
            // Only include if URL exists and is local
            if ($url && $this->is_local_image_url($url)) {
                $images[] = [
                    'uuid'  => $this->get_crm_uuid_for_attachment($featured_id),
                    'url'   => $url,
                    'order' => 1,
                    'name'  => get_the_title($featured_id),
                ];
            }
        }

        // Get gallery images (Houzez stores as multiple meta entries with same key)
        $gallery = get_post_meta($property_id, 'fave_property_images', false);
        if (!empty($gallery)) {
            $attachment_ids = $gallery;
            $order = count($images) + 1;

            foreach ($attachment_ids as $attachment_id) {
                $attachment_id = (int) trim($attachment_id);
                if ($attachment_id && $attachment_id !== $featured_id) {
                    $url = wp_get_attachment_url($attachment_id);
                    // Only include if URL exists and is local
                    if ($url && $this->is_local_image_url($url)) {
                        $images[] = [
                            'uuid'  => $this->get_crm_uuid_for_attachment($attachment_id),
                            'url'   => $url,
                            'order' => $order++,
                            'name'  => get_the_title($attachment_id),
                        ];
                    }
                }
            }
        }

        return $images;
    }

    /**
     * Get CRM UUID for a WordPress attachment.
     *
     * @param int $attachment_id Attachment ID.
     * @return string|null CRM media UUID or null.
     */
    private function get_crm_uuid_for_attachment($attachment_id) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table lookup
        return $wpdb->get_var(
            $wpdb->prepare(
                "SELECT crm_uuid FROM {$wpdb->prefix}hcrm_entity_map WHERE entity_type = 'media' AND wp_id = %d",
                $attachment_id
            )
        );
    }

    /**
     * Get property floor plans.
     *
     * @param int $property_id Property ID.
     * @return array Floor plans data.
     */
    public function get_floor_plans($property_id) {
        $plans = [];

        // Houzez stores floor plans as a repeater-style meta
        $floor_plans = get_post_meta($property_id, 'floor_plans', true);

        if (!empty($floor_plans) && is_array($floor_plans)) {
            foreach ($floor_plans as $index => $plan) {
                // Extract numeric value from strings like "670 Sqft"
                $rooms = isset($plan['fave_plan_rooms']) ? $this->extract_numeric($plan['fave_plan_rooms']) : null;
                $bathrooms = isset($plan['fave_plan_bathrooms']) ? $this->extract_numeric($plan['fave_plan_bathrooms']) : null;
                $size = isset($plan['fave_plan_size']) ? $this->extract_numeric($plan['fave_plan_size']) : null;

                $plan_data = [
                    'uuid'             => $plan['crm_uuid'] ?? null,
                    'plan_title'       => $plan['fave_plan_title'] ?? '',
                    'plan_description' => $plan['fave_plan_description'] ?? '',
                    'bedrooms'         => $rooms,
                    'bathrooms'        => $bathrooms,
                    'area_size'        => $size,
                ];

                // Add image URL if exists and is a valid local URL
                if (!empty($plan['fave_plan_image'])) {
                    $image_url = $plan['fave_plan_image'];
                    // Only include if it's a local WordPress URL (not external demo URLs)
                    if ($this->is_local_image_url($image_url)) {
                        $plan_data['image_url'] = $image_url;
                    } else {
                        HCRM_Logger::warning(sprintf(
                            'Skipping external floor plan image for property %d: %s',
                            $property_id,
                            $image_url
                        ));
                    }
                }

                $plans[] = $plan_data;
            }
        }

        return $plans;
    }

    /**
     * Check if an image URL is a local WordPress URL.
     *
     * @param string $url Image URL to check.
     * @return bool True if local, false if external.
     */
    private function is_local_image_url($url) {
        if (empty($url)) {
            return false;
        }

        // Get site URL host
        $site_host = wp_parse_url( home_url(), PHP_URL_HOST );
        $image_host = wp_parse_url( $url, PHP_URL_HOST );

        // Check if same host
        if ($site_host && $image_host && $site_host === $image_host) {
            return true;
        }

        // Check for relative URLs (start with /)
        if (strpos($url, '/') === 0 && strpos($url, '//') !== 0) {
            return true;
        }

        // Known demo/external domains to skip
        $external_domains = [
            'sandbox.favethemes.com',
            'developer.developer.developer', // placeholder domains
            'demo.houzez.developer',
            'placehold.co',
            'placeholder.com',
            'via.placeholder.com',
        ];

        foreach ($external_domains as $domain) {
            if ($image_host && stripos($image_host, $domain) !== false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Extract numeric value from a string like "670 Sqft".
     *
     * @param mixed $value Value to extract number from.
     * @return float|null Numeric value or null.
     */
    private function extract_numeric($value) {
        if (is_numeric($value)) {
            return (float) $value;
        }
        if (is_string($value)) {
            preg_match('/[\d,.]+/', $value, $matches);
            if (!empty($matches[0])) {
                return (float) str_replace(',', '', $matches[0]);
            }
        }
        return null;
    }

    /**
     * Get property agent/agency data.
     *
     * @param int $property_id Property ID.
     * @return array Agent and agency data.
     */
    public function get_property_agent_data($property_id) {
        $result = [
            'assignees' => [],
            'agency'    => null,
        ];

        // Get display option
        $display_option = get_post_meta($property_id, 'fave_agent_display_option', true);

        if ($display_option === 'agent_info') {
            // Get assigned agents
            $agents = get_post_meta($property_id, 'fave_agents', true);
            if (!empty($agents)) {
                $agent_ids = is_array($agents) ? $agents : [$agents];
                $is_first = true;

                foreach ($agent_ids as $agent_id) {
                    $agent_id = (int) $agent_id;
                    if ($agent_id) {
                        $agent_data = $this->get_agent_data($agent_id, $is_first);
                        if ($agent_data) {
                            $result['assignees'][] = $agent_data;
                            $is_first = false;
                        }
                    }
                }
            }
        } elseif ($display_option === 'agency_info') {
            // Get agency
            $agency_id = get_post_meta($property_id, 'fave_property_agency', true);
            if ($agency_id) {
                $result['agency'] = $this->get_agency_data((int) $agency_id);
            }
        } elseif ($display_option === 'author_info') {
            // Get post author as assignee
            $post = get_post($property_id);
            if ($post) {
                $user = get_user_by('ID', $post->post_author);
                if ($user) {
                    $result['assignees'][] = [
                        'uuid'       => $this->get_user_crm_uuid($user->ID),
                        'name'       => $user->display_name,
                        'email'      => $user->user_email,
                        'is_primary' => true,
                        'role'       => 'agent',
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * Get agent data by post ID.
     *
     * @param int  $agent_id   Agent post ID.
     * @param bool $is_primary Whether this is the primary agent.
     * @return array|null Agent data or null.
     */
    private function get_agent_data($agent_id, $is_primary = false) {
        $agent_post = get_post($agent_id);
        if (!$agent_post || $agent_post->post_type !== 'houzez_agent') {
            return null;
        }

        $email = get_post_meta($agent_id, 'fave_agent_email', true);
        $avatar_url = get_the_post_thumbnail_url($agent_id, 'thumbnail');

        // Only include avatar if it's a local URL
        $avatar = ($avatar_url && $this->is_local_image_url($avatar_url)) ? $avatar_url : null;

        return [
            'uuid'       => $this->get_entity_crm_uuid($agent_id, 'agent'),
            'name'       => $agent_post->post_title,
            'email'      => $email ?: '',
            'avatar'     => $avatar,
            'is_primary' => $is_primary,
            'role'       => 'agent',
        ];
    }

    /**
     * Get agency data by post ID.
     *
     * @param int $agency_id Agency post ID.
     * @return array|null Agency data or null.
     */
    private function get_agency_data($agency_id) {
        $agency_post = get_post($agency_id);
        if (!$agency_post || $agency_post->post_type !== 'houzez_agency') {
            return null;
        }

        $logo_url = get_the_post_thumbnail_url($agency_id, 'thumbnail');
        // Only include logo if it's a local URL
        $logo = ($logo_url && $this->is_local_image_url($logo_url)) ? $logo_url : null;

        return [
            'uuid'     => $this->get_entity_crm_uuid($agency_id, 'agency'),
            'name'     => $agency_post->post_title,
            'slug'     => $agency_post->post_name,
            'logo_url' => $logo,
        ];
    }

    /**
     * Create or update a WordPress property from CRM data.
     *
     * @param array    $crm_data    CRM listing data.
     * @param int|null $property_id Existing property ID or null for new.
     * @return int|WP_Error Property ID or error.
     */
    public function create_or_update_from_crm($crm_data, $property_id = null) {
        // Convert CRM data to Houzez format
        $houzez_data = $this->mapper->crm_to_houzez($crm_data);

        // Prepare post data
        $post_data = [
            'post_type'    => 'property',
            'post_status'  => 'publish',
            'post_title'   => $crm_data['title'] ?? '',
            'post_name'    => $crm_data['slug'] ?? '',
            'post_content' => $crm_data['detail']['description'] ?? '',
        ];

        if ($property_id) {
            $post_data['ID'] = $property_id;
            $result = wp_update_post($post_data, true);
        } else {
            $result = wp_insert_post($post_data, true);
        }

        if (is_wp_error($result)) {
            return $result;
        }

        $property_id = $result;

        // Update meta fields
        foreach ($houzez_data['meta'] as $key => $value) {
            update_post_meta($property_id, $key, $value);
        }

        // Update taxonomies
        foreach ($houzez_data['taxonomies'] as $taxonomy => $terms) {
            if (!empty($terms)) {
                wp_set_object_terms($property_id, $terms, $taxonomy);
            }
        }

        // Sync images if provided
        if (!empty($crm_data['images'])) {
            $this->sync_images_from_crm($property_id, $crm_data['images']);
        }

        // Sync floor plans if provided
        if (isset($crm_data['floor_plans'])) {
            $this->sync_floor_plans_from_crm($property_id, $crm_data['floor_plans']);
        }

        return $property_id;
    }

    /**
     * Sync images from CRM to WordPress property.
     *
     * This method handles complete image synchronization:
     * - Downloads new images that don't exist locally
     * - Removes images that are no longer in the CRM
     * - Updates gallery order and featured image
     *
     * @param int   $property_id Property ID.
     * @param array $images      Array of image data with 'url', 'order', 'is_featured'.
     */
    private function sync_images_from_crm($property_id, $images) {
        // Handle empty images - remove all existing images
        if (empty($images) || !is_array($images)) {
            $this->clear_property_images($property_id);
            return;
        }

        // Get current attachments with their source URLs
        $current_attachments = $this->get_property_attachments_with_sources($property_id);

        // Build array of new image URLs
        $new_urls = array_column($images, 'url');

        // Find attachments to remove (exist locally but not in new list)
        $urls_to_keep = [];
        foreach ($current_attachments as $attachment_id => $source_url) {
            if (!in_array($source_url, $new_urls, true)) {
                // This image was removed from CRM - delete it
                wp_delete_attachment($attachment_id, true);
                HCRM_Logger::info(sprintf(
                    'Deleted removed image attachment %d for property %d',
                    $attachment_id,
                    $property_id
                ));
            } else {
                $urls_to_keep[$source_url] = $attachment_id;
            }
        }

        // Clear existing gallery meta (will rebuild)
        delete_post_meta($property_id, 'fave_property_images');
        delete_post_thumbnail($property_id);

        // Sort images by order
        usort($images, function($a, $b) {
            return ($a['order'] ?? 0) - ($b['order'] ?? 0);
        });

        $gallery_ids = [];
        $featured_set = false;

        foreach ($images as $image) {
            $url = $image['url'] ?? '';
            if (empty($url)) {
                HCRM_Logger::warning(sprintf('Empty URL in image data for property %d: %s', $property_id, wp_json_encode($image)));
                continue;
            }

            // Log the URL being processed
            HCRM_Logger::info(sprintf('Processing image URL for property %d: %s', $property_id, $url));

            // Check if we already have this image
            $attachment_id = $urls_to_keep[$url] ?? $this->get_attachment_by_url($url);

            if (!$attachment_id) {
                // Download and create attachment
                $attachment_id = $this->sideload_image($url, $property_id);
            }

            if (!$attachment_id || is_wp_error($attachment_id)) {
                HCRM_Logger::warning(sprintf(
                    'Failed to sideload image for property %d: %s',
                    $property_id,
                    is_wp_error($attachment_id) ? $attachment_id->get_error_message() : 'Unknown error'
                ));
                continue;
            }

            // Set featured image (first one or one marked as featured)
            if (!$featured_set && (!empty($image['is_featured']) || count($gallery_ids) === 0)) {
                set_post_thumbnail($property_id, $attachment_id);
                $featured_set = true;
            }

            // Add to gallery
            $gallery_ids[] = $attachment_id;
            add_post_meta($property_id, 'fave_property_images', $attachment_id);
        }

        HCRM_Logger::info(sprintf(
            'Synced %d images for property %d',
            count($gallery_ids),
            $property_id
        ));
    }

    /**
     * Get all property attachments with their source URLs.
     *
     * @param int $property_id Property ID.
     * @return array Associative array of attachment_id => source_url.
     */
    private function get_property_attachments_with_sources($property_id) {
        $attachments = [];

        // Get featured image
        $featured_id = get_post_thumbnail_id($property_id);
        if ($featured_id) {
            $source_url = get_post_meta($featured_id, '_hcrm_source_url', true);
            if ($source_url) {
                $attachments[$featured_id] = $source_url;
            }
        }

        // Get gallery images
        $gallery = get_post_meta($property_id, 'fave_property_images', false);
        foreach ($gallery as $attachment_id) {
            if ($attachment_id && !isset($attachments[$attachment_id])) {
                $source_url = get_post_meta($attachment_id, '_hcrm_source_url', true);
                if ($source_url) {
                    $attachments[$attachment_id] = $source_url;
                }
            }
        }

        return $attachments;
    }

    /**
     * Clear all images from a property.
     *
     * @param int $property_id Property ID.
     */
    private function clear_property_images($property_id) {
        // Get all attachments
        $attachments = $this->get_property_attachments_with_sources($property_id);

        // Delete each attachment that came from CRM
        foreach ($attachments as $attachment_id => $source_url) {
            if ($source_url) { // Only delete if it has a CRM source
                wp_delete_attachment($attachment_id, true);
            }
        }

        // Clear meta
        delete_post_thumbnail($property_id);
        delete_post_meta($property_id, 'fave_property_images');

        HCRM_Logger::info(sprintf(
            'Cleared all images for property %d',
            $property_id
        ));
    }

    /**
     * Get attachment ID by source URL (stored in meta).
     *
     * @param string $url Source URL.
     * @return int|null Attachment ID or null.
     */
    private function get_attachment_by_url($url) {
        global $wpdb;

        // Try to find by source URL in postmeta
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Attachment lookup by URL
        $attachment_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_hcrm_source_url' AND meta_value = %s LIMIT 1",
            $url
        ));

        return $attachment_id ? (int) $attachment_id : null;
    }

    /**
     * Sideload an image from URL.
     *
     * @param string $url         Image URL.
     * @param int    $property_id Parent property ID.
     * @return int|WP_Error Attachment ID or error.
     */
    private function sideload_image($url, $property_id) {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        // Validate URL
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return new WP_Error('invalid_url', 'Invalid image URL provided');
        }

        // Download file to temp location with SSL verification disabled for local dev
        $tmp = $this->download_image_url($url);

        if (is_wp_error($tmp)) {
            return $tmp;
        }

        // Get file info
        $file_array = [
            'name'     => basename( wp_parse_url( $url, PHP_URL_PATH ) ),
            'tmp_name' => $tmp,
        ];

        // Sideload the file
        $attachment_id = media_handle_sideload($file_array, $property_id);

        // Clean up temp file
        if (file_exists($tmp)) {
            wp_delete_file( $tmp );
        }

        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        // Store source URL for future reference
        update_post_meta($attachment_id, '_hcrm_source_url', $url);

        return $attachment_id;
    }

    /**
     * Download image from URL with SSL verification disabled for local development.
     *
     * @param string $url Image URL.
     * @return string|WP_Error Path to temp file or error.
     */
    private function download_image_url($url) {
        // For local .test domains, disable SSL verification
        $is_local = (
            strpos($url, '.test/') !== false ||
            strpos($url, '.local/') !== false ||
            strpos($url, 'localhost') !== false
        );

        // Try standard download first
        $tmp = download_url($url, 300, !$is_local);

        if (!is_wp_error($tmp)) {
            return $tmp;
        }

        // If failed and is local, try with SSL verification disabled
        if ($is_local) {
            HCRM_Logger::info(sprintf('Retrying download with SSL verification disabled for: %s', $url));

            // Manual download with SSL verification disabled
            $response = wp_remote_get($url, [
                'timeout'   => 300,
                'sslverify' => false,
            ]);

            if (is_wp_error($response)) {
                return $response;
            }

            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                return new WP_Error('http_error', sprintf('HTTP %d error downloading image', $response_code));
            }

            $body = wp_remote_retrieve_body($response);
            if (empty($body)) {
                return new WP_Error('empty_body', 'Empty response body');
            }

            // Create temp file
            $tmpfname = wp_tempnam($url);
            if (!$tmpfname) {
                return new WP_Error('temp_file', 'Could not create temp file');
            }

            // Write content to temp file
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Writing to temp file for sideload
            $fp = fopen( $tmpfname, 'wb' );
            if ( ! $fp ) {
                wp_delete_file( $tmpfname );
                return new WP_Error( 'file_write', 'Could not write to temp file' );
            }

            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Writing to temp file for sideload
            fwrite( $fp, $body );
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing temp file
            fclose( $fp );

            return $tmpfname;
        }

        return $tmp;
    }

    /**
     * Sync floor plans from CRM to WordPress property.
     *
     * @param int   $property_id Property ID.
     * @param array $floor_plans Array of floor plan data from CRM.
     */
    private function sync_floor_plans_from_crm($property_id, $floor_plans) {
        if (!is_array($floor_plans)) {
            // If floor_plans is empty array, clear existing floor plans
            if (empty($floor_plans)) {
                delete_post_meta($property_id, 'floor_plans');
                update_post_meta($property_id, 'fave_floor_plans_enable', 'disable');
                HCRM_Logger::info(sprintf('Cleared floor plans for property %d', $property_id));
            }
            return;
        }

        $wp_floor_plans = [];

        foreach ($floor_plans as $fp) {
            $image_url = '';

            // Download floor plan image if provided
            if (!empty($fp['image_url'])) {
                // Check if we already have this image
                $attachment_id = $this->get_attachment_by_url($fp['image_url']);

                if (!$attachment_id) {
                    // Download and create attachment
                    $attachment_id = $this->sideload_image($fp['image_url'], $property_id);
                }

                if ($attachment_id && !is_wp_error($attachment_id)) {
                    $image_url = wp_get_attachment_url($attachment_id);
                } else {
                    HCRM_Logger::warning(sprintf(
                        'Failed to download floor plan image for property %d: %s',
                        $property_id,
                        is_wp_error($attachment_id) ? $attachment_id->get_error_message() : 'Unknown error'
                    ));
                }
            }

            $wp_floor_plans[] = [
                'fave_plan_title'         => $fp['plan_title'] ?? '',
                'fave_plan_description'   => $fp['plan_description'] ?? '',
                'fave_plan_rooms'         => $fp['bedrooms'] ?? '',
                'fave_plan_bathrooms'     => $fp['bathrooms'] ?? '',
                'fave_plan_size'          => $fp['area_size'] ?? '',
                'fave_plan_price'         => $fp['price'] ?? '',
                'fave_plan_price_postfix' => $fp['price_postfix'] ?? '',
                'fave_plan_image'         => $image_url,
                'crm_uuid'                => $fp['uuid'] ?? '',
            ];
        }

        // Update floor plans meta
        update_post_meta($property_id, 'floor_plans', $wp_floor_plans);

        // Enable/disable floor plans display based on whether we have any
        if (!empty($wp_floor_plans)) {
            update_post_meta($property_id, 'fave_floor_plans_enable', 'enable');
        } else {
            update_post_meta($property_id, 'fave_floor_plans_enable', 'disable');
        }

        HCRM_Logger::info(sprintf(
            'Synced %d floor plans for property %d',
            count($wp_floor_plans),
            $property_id
        ));
    }

    /**
     * Get CRM UUID for an entity.
     *
     * @param int    $wp_id       WordPress ID.
     * @param string $entity_type Entity type.
     * @return string|null CRM UUID or null.
     */
    private function get_entity_crm_uuid($wp_id, $entity_type) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table lookup
        $uuid = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT crm_uuid FROM {$wpdb->prefix}hcrm_entity_map WHERE entity_type = %s AND wp_id = %d",
                $entity_type,
                $wp_id
            )
        );

        return $uuid ?: null;
    }

    /**
     * Get CRM UUID for a user.
     *
     * @param int $user_id WordPress user ID.
     * @return string|null CRM UUID or null.
     */
    private function get_user_crm_uuid($user_id) {
        return $this->get_entity_crm_uuid($user_id, 'user');
    }

    /**
     * Calculate a hash for property data for change detection.
     *
     * @param int $property_id Property ID.
     * @return string MD5 hash.
     */
    public function calculate_sync_hash($property_id) {
        $data = $this->prepare_for_api($property_id);

        // Remove volatile fields
        unset($data['published_at']);

        return md5(wp_json_encode($data));
    }
}
