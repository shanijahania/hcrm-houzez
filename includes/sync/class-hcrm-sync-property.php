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

        // Add mapped custom fields from Houzez fields builder
        $mapped_custom_fields = HCRM_Custom_Fields_Mapper::map_houzez_to_crm( $property_id );
        if ( ! empty( $mapped_custom_fields ) ) {
            if ( ! isset( $data['custom_fields'] ) ) {
                $data['custom_fields'] = [];
            }
            // Merge mapped fields as { slug: value } format
            $data['custom_fields'] = array_merge( $data['custom_fields'], $mapped_custom_fields );
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
     * Includes:
     * - crm_uuid: CRM media UUID (from _hcrm_crm_uuid meta) for matching existing images
     * - wp_attachment_id: WordPress attachment ID for mapping new images back
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
                $crm_uuid = get_post_meta($featured_id, '_hcrm_crm_uuid', true);
                $images[] = [
                    'url'              => $url,
                    'order'            => 1,
                    'name'             => get_post_field('post_name', $featured_id),
                    'crm_uuid'         => $crm_uuid ?: null,
                    'wp_attachment_id' => $featured_id,
                ];
            }
        }

        // Get gallery images (Houzez stores as multiple meta entries with same key)
        $gallery = get_post_meta($property_id, 'fave_property_images', false);
        if (!empty($gallery)) {
            $order = count($images) + 1;

            foreach ($gallery as $attachment_id) {
                $attachment_id = (int) trim($attachment_id);
                if ($attachment_id && $attachment_id !== $featured_id) {
                    $url = wp_get_attachment_url($attachment_id);
                    // Only include if URL exists and is local
                    if ($url && $this->is_local_image_url($url)) {
                        $crm_uuid = get_post_meta($attachment_id, '_hcrm_crm_uuid', true);
                        $images[] = [
                            'url'              => $url,
                            'order'            => $order++,
                            'name'             => get_post_field('post_name', $attachment_id),
                            'crm_uuid'         => $crm_uuid ?: null,
                            'wp_attachment_id' => $attachment_id,
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
     * Uses the centralized get_crm_uuid() method for consistent query logic.
     *
     * @param int $attachment_id Attachment ID.
     * @return string|null CRM media UUID or null.
     */
    private function get_crm_uuid_for_attachment($attachment_id) {
        return HCRM_Sync_Manager::get_instance()->get_crm_uuid($attachment_id, 'media');
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
     * Generate a match key for floor plan deduplication.
     * Uses title + size for fallback matching when UUID is not available.
     *
     * @param string|null $title Floor plan title.
     * @param mixed       $size  Floor plan area size.
     * @return string Match key.
     */
    private function generate_floor_plan_match_key($title, $size) {
        $normalized_title = strtolower(trim($title ?? ''));
        $normalized_size = is_numeric($size) ? (float) $size : 0.0;

        return $normalized_title . '_' . $normalized_size;
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

        // Apply mapped custom fields from CRM
        if ( ! empty( $crm_data['custom_fields'] ) && is_array( $crm_data['custom_fields'] ) ) {
            $mapped_meta = HCRM_Custom_Fields_Mapper::map_crm_to_houzez( $crm_data['custom_fields'] );
            foreach ( $mapped_meta as $meta_key => $meta_value ) {
                update_post_meta( $property_id, $meta_key, $meta_value );
            }
        }

        return $property_id;
    }

    /**
     * Sync images from CRM to WordPress property.
     *
     * Uses UUID-based matching (CRM media UUID) as primary strategy:
     * - Primary: Match by CRM UUID (_hcrm_crm_uuid meta = image.uuid from webhook)
     * - Fallback: Match by name for legacy images without UUID
     * - Downloads new images that don't exist locally
     * - Deletes images that originated from CRM but are no longer in the list
     *
     * @param int   $property_id Property ID.
     * @param array $images      Array of image data with 'uuid', 'url', 'name'.
     */
    private function sync_images_from_crm($property_id, $images) {
        if (empty($images) || !is_array($images)) {
            HCRM_Logger::info("sync_images_from_crm: Empty images array for property {$property_id}, skipping");
            return;
        }

        // Build lookup by CRM UUID
        $existing_by_uuid = [];
        $existing_by_name = [];
        $all_wp_attachment_ids = [];

        // Get all property attachments
        $featured_id = get_post_thumbnail_id($property_id);
        if ($featured_id) {
            $all_wp_attachment_ids[] = $featured_id;
            $crm_uuid = get_post_meta($featured_id, '_hcrm_crm_uuid', true);
            $post_name = get_post_field('post_name', $featured_id);
            $crm_name = get_post_meta($featured_id, '_hcrm_crm_name', true);

            if ($crm_uuid) {
                $existing_by_uuid[$crm_uuid] = $featured_id;
            }
            if ($post_name) {
                $existing_by_name[$post_name] = $featured_id;
            }
            if ($crm_name) {
                $existing_by_name[$crm_name] = $featured_id;
            }
        }

        $gallery = get_post_meta($property_id, 'fave_property_images', false);
        foreach ($gallery as $attachment_id) {
            if ($attachment_id) {
                $all_wp_attachment_ids[] = (int) $attachment_id;
                $crm_uuid = get_post_meta($attachment_id, '_hcrm_crm_uuid', true);
                $post_name = get_post_field('post_name', $attachment_id);
                $crm_name = get_post_meta($attachment_id, '_hcrm_crm_name', true);

                if ($crm_uuid) {
                    $existing_by_uuid[$crm_uuid] = (int) $attachment_id;
                }
                if ($post_name) {
                    $existing_by_name[$post_name] = (int) $attachment_id;
                }
                if ($crm_name) {
                    $existing_by_name[$crm_name] = (int) $attachment_id;
                }
            }
        }

        // Get all incoming CRM image UUIDs
        $incoming_uuids = [];
        foreach ($images as $image) {
            $uuid = $image['uuid'] ?? null;
            if ($uuid) {
                $incoming_uuids[] = $uuid;
            }
        }

        HCRM_Logger::info(sprintf(
            'sync_images_from_crm: WP has %d images by UUID: %s | CRM sending %d images with UUIDs: %s',
            count($existing_by_uuid),
            wp_json_encode(array_keys($existing_by_uuid)),
            count($images),
            wp_json_encode($incoming_uuids)
        ));

        // STEP 1: DELETE WP images that are no longer in CRM (by UUID)
        foreach ($all_wp_attachment_ids as $attachment_id) {
            $crm_uuid = get_post_meta($attachment_id, '_hcrm_crm_uuid', true);

            // Only consider images that have a CRM UUID (synced from CRM)
            if (!$crm_uuid) {
                continue; // No UUID = either WP-native or legacy image, don't delete by UUID
            }

            // Check if this image is still in CRM's list (by UUID)
            if (!in_array($crm_uuid, $incoming_uuids, true)) {
                HCRM_Logger::info(sprintf(
                    'Deleting image no longer in CRM: attachment_id=%d, crm_uuid=%s',
                    $attachment_id,
                    $crm_uuid
                ));

                // Remove from gallery meta
                delete_post_meta($property_id, 'fave_property_images', $attachment_id);

                // Remove featured image if it matches
                if ((int) get_post_thumbnail_id($property_id) === (int) $attachment_id) {
                    delete_post_thumbnail($property_id);
                }

                // Delete the attachment
                wp_delete_attachment($attachment_id, true);
            }
        }

        // STEP 2: ADD new images from CRM (skip existing by UUID)
        foreach ($images as $image) {
            $url = $image['url'] ?? '';
            $uuid = $image['uuid'] ?? null;
            $name = $image['name'] ?? null;
            $wp_attachment_id = $image['wp_attachment_id'] ?? null;

            if (empty($url)) {
                continue;
            }

            // Strategy 1: Match by UUID (primary)
            if ($uuid && isset($existing_by_uuid[$uuid])) {
                HCRM_Logger::info(sprintf('Image exists by UUID: %s', $uuid));
                continue;
            }

            // Strategy 2: Match by name (fallback for legacy images without UUID)
            if (!$uuid && $name && isset($existing_by_name[$name])) {
                // Found by name - update with UUID for future matching
                $attachment_id = $existing_by_name[$name];
                if ($uuid) {
                    update_post_meta($attachment_id, '_hcrm_crm_uuid', $uuid);
                }
                HCRM_Logger::info(sprintf('Image exists by name: %s (legacy)', $name));
                continue;
            }

            // Strategy 3: Match by wp_attachment_id (for images synced from WP→CRM)
            if ($wp_attachment_id && in_array((int) $wp_attachment_id, $all_wp_attachment_ids, true)) {
                // Found by original WP ID - store UUID for future matching
                if ($uuid) {
                    update_post_meta($wp_attachment_id, '_hcrm_crm_uuid', $uuid);
                }
                HCRM_Logger::info(sprintf('Image matched by wp_attachment_id: %d, stored UUID: %s', $wp_attachment_id, $uuid ?: 'none'));
                continue;
            }

            // New image from CRM - download it
            HCRM_Logger::info(sprintf('Downloading new image: %s (uuid: %s, name: %s)', $url, $uuid ?: 'none', $name ?: 'none'));
            $attachment_id = $this->sideload_image($url, $property_id);

            if ($attachment_id && !is_wp_error($attachment_id)) {
                // Store CRM UUID for future matching
                if ($uuid) {
                    update_post_meta($attachment_id, '_hcrm_crm_uuid', $uuid);
                }

                // Store source URL
                update_post_meta($attachment_id, '_hcrm_source_url', $url);

                // Store CRM's name field for legacy compatibility
                if ($name) {
                    update_post_meta($attachment_id, '_hcrm_crm_name', $name);
                }

                add_post_meta($property_id, 'fave_property_images', $attachment_id);
                HCRM_Logger::info(sprintf('Image downloaded and attached: %d (crm_uuid: %s)', $attachment_id, $uuid ?: 'none'));
            } else {
                HCRM_Logger::warning(sprintf(
                    'Failed to sideload image: %s',
                    is_wp_error($attachment_id) ? $attachment_id->get_error_message() : 'Unknown error'
                ));
            }
        }

        HCRM_Logger::info(sprintf(
            'sync_images_from_crm: Processed %d images for property %d',
            count($images),
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
     * Get existing images for property indexed by normalized URL.
     *
     * Builds a lookup table of all images attached to a property,
     * indexed by both their WordPress URL and CRM source URL (if any).
     *
     * @param int $property_id Property ID.
     * @return array Associative array of normalized_url => attachment_id.
     */
    private function get_existing_images_by_url($property_id) {
        $result = [];

        // Get featured image
        $featured_id = get_post_thumbnail_id($property_id);
        if ($featured_id) {
            // Add WordPress URL
            $url = wp_get_attachment_url($featured_id);
            if ($url) {
                $result[$this->normalize_image_url($url)] = $featured_id;
            }
            // Add CRM source URL if exists
            $source = get_post_meta($featured_id, '_hcrm_source_url', true);
            if ($source) {
                $result[$this->normalize_image_url($source)] = $featured_id;
            }
        }

        // Get gallery images
        $gallery = get_post_meta($property_id, 'fave_property_images', false);
        if (!empty($gallery)) {
            foreach ($gallery as $attachment_id) {
                if (!$attachment_id) {
                    continue;
                }
                // Add WordPress URL
                $url = wp_get_attachment_url($attachment_id);
                if ($url) {
                    $result[$this->normalize_image_url($url)] = (int) $attachment_id;
                }
                // Add CRM source URL if exists
                $source = get_post_meta($attachment_id, '_hcrm_source_url', true);
                if ($source) {
                    $result[$this->normalize_image_url($source)] = (int) $attachment_id;
                }
            }
        }

        return $result;
    }

    /**
     * Normalize URL for comparison.
     *
     * Removes protocol, query strings, and trailing slashes for reliable matching.
     *
     * @param string $url URL to normalize.
     * @return string Normalized URL.
     */
    private function normalize_image_url($url) {
        // Remove protocol (http:// or https://)
        $url = preg_replace('#^https?://#', '', $url);
        // Remove query strings
        $url = strtok($url, '?');
        // Remove trailing slashes
        $url = rtrim($url, '/');
        return $url;
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
     * Preserves existing crm_uuid values and uses title+size matching as fallback.
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

        // Get existing floor plans from WordPress to preserve crm_uuid values
        $existing_plans = get_post_meta($property_id, 'floor_plans', true);
        $existing_plans = is_array($existing_plans) ? $existing_plans : [];

        // Build lookup maps for existing plans
        $existing_by_uuid = [];
        $existing_by_match_key = [];

        foreach ($existing_plans as $index => $plan) {
            // Index by UUID if available
            if (!empty($plan['crm_uuid'])) {
                $existing_by_uuid[$plan['crm_uuid']] = $index;
            }

            // Also index by match key (title + size)
            $size = $this->extract_numeric($plan['fave_plan_size'] ?? '');
            $match_key = $this->generate_floor_plan_match_key(
                $plan['fave_plan_title'] ?? '',
                $size
            );
            $existing_by_match_key[$match_key] = $index;
        }

        $wp_floor_plans = [];
        $preserved_uuids = 0;

        foreach ($floor_plans as $fp) {
            $image_url = '';
            $preserved_uuid = null;

            // Strategy 1: Match by UUID from CRM data
            if (!empty($fp['uuid']) && isset($existing_by_uuid[$fp['uuid']])) {
                $preserved_uuid = $fp['uuid'];
            } else {
                // Strategy 2: Match by title+size
                $incoming_size = $this->extract_numeric($fp['area_size'] ?? null);
                $incoming_match_key = $this->generate_floor_plan_match_key(
                    $fp['plan_title'] ?? '',
                    $incoming_size
                );

                if (isset($existing_by_match_key[$incoming_match_key])) {
                    $existing_index = $existing_by_match_key[$incoming_match_key];
                    // Preserve the existing crm_uuid if it was set
                    $preserved_uuid = $existing_plans[$existing_index]['crm_uuid'] ?? ($fp['uuid'] ?? '');
                    $preserved_uuids++;
                } else {
                    // New floor plan - use UUID from CRM if available
                    $preserved_uuid = $fp['uuid'] ?? '';
                }
            }

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
                'crm_uuid'                => $preserved_uuid,
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
            'Synced %d floor plans for property %d (preserved %d UUIDs via title+size matching)',
            count($wp_floor_plans),
            $property_id,
            $preserved_uuids
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
