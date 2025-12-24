<?php
/**
 * Data Mapper class for transforming data between Houzez and CRM formats.
 *
 * @package HCRM_Houzez
 * @since   1.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Class HCRM_Data_Mapper
 *
 * Maps data fields between Houzez meta fields and CRM API format.
 *
 * @since 1.0.0
 */
class HCRM_Data_Mapper {

    /**
     * Field mappings: Houzez meta key => CRM API path.
     *
     * @var array
     */
    const FIELD_MAP = [
        // Pricing
        'fave_property_price'           => 'price',
        'fave_property_sec_price'       => 'second_price',
        'fave_property_price_prefix'    => 'price_prefix',
        'fave_property_price_postfix'   => 'price_postfix',
        'fave_currency'                 => 'currency_code',

        // Property Details
        'fave_property_size'            => 'detail.area_size',
        'fave_property_size_prefix'     => 'detail.area_unit',
        'fave_property_bedrooms'        => 'detail.bedrooms',
        'fave_property_bathrooms'       => 'detail.bathrooms',
        'fave_property_rooms'           => 'detail.rooms',
        'fave_property_garage'          => 'detail.garage',
        'fave_property_garage_size'     => 'detail.garage_size',
        'fave_property_year'            => 'detail.year_built',
        'fave_property_land'            => 'detail.land_area',
        'fave_property_land_postfix'    => 'detail.land_unit',

        // Property ID
        'fave_property_id'              => 'listing_id',

        // Address
        'fave_property_address'         => 'address.address_line1',
        'fave_property_zip'             => 'address.postal_code',

        // Settings
        'fave_featured'                 => 'is_featured',
    ];

    /**
     * Taxonomy mappings: WordPress taxonomy => CRM API path.
     *
     * @var array
     */
    const TAXONOMY_MAP = [
        'property_type'    => 'listing_type',
        'property_status'  => 'status',
        'property_label'   => 'listing_label',
        'property_city'    => 'address.city',
        'property_state'   => 'address.state',
        'property_country' => 'address.country',
        'property_area'    => 'address.area',
        'property_feature' => 'facilities',
    ];

    /**
     * Convert Houzez data to CRM API format.
     *
     * @param array $houzez_data Data including 'meta' and 'taxonomies'.
     * @return array CRM-formatted data.
     */
    public function houzez_to_crm($houzez_data) {
        $crm_data = [];
        $meta = $houzez_data['meta'] ?? [];
        $taxonomies = $houzez_data['taxonomies'] ?? [];

        // Map meta fields
        foreach (self::FIELD_MAP as $houzez_key => $crm_path) {
            if (isset($meta[$houzez_key]) && $meta[$houzez_key] !== '') {
                $value = $meta[$houzez_key];
                $this->set_nested_value($crm_data, $crm_path, $this->transform_value($houzez_key, $value, 'to_crm'));
            }
        }

        // Map taxonomies
        foreach (self::TAXONOMY_MAP as $taxonomy => $crm_path) {
            if (isset($taxonomies[$taxonomy]) && !empty($taxonomies[$taxonomy])) {
                $terms = $taxonomies[$taxonomy];
                $crm_value = $this->transform_taxonomy($taxonomy, $terms, 'to_crm');

                if ($crm_path === 'facilities') {
                    // Facilities is an array of objects
                    $crm_data['facilities'] = $crm_value;
                } elseif (strpos($crm_path, 'address.') === 0) {
                    // Location fields under address
                    $field = str_replace('address.', '', $crm_path);
                    if (!isset($crm_data['address'])) {
                        $crm_data['address'] = [];
                    }
                    $crm_data['address'][$field] = $crm_value;
                } else {
                    // Single taxonomy (status, type, label)
                    $crm_data[$crm_path] = $crm_value;
                }
            }
        }

        // Handle location coordinates
        if (isset($meta['fave_property_location']) && !empty($meta['fave_property_location'])) {
            $coords = $this->parse_coordinates($meta['fave_property_location']);
            if ($coords) {
                if (!isset($crm_data['address'])) {
                    $crm_data['address'] = [];
                }
                $crm_data['address']['latitude'] = $coords['lat'];
                $crm_data['address']['longitude'] = $coords['lng'];
            }
        }

        return $crm_data;
    }

    /**
     * Convert CRM data to Houzez format.
     *
     * @param array $crm_data CRM API response data.
     * @return array Houzez-formatted data with 'meta' and 'taxonomies'.
     */
    public function crm_to_houzez($crm_data) {
        $meta = [];
        $taxonomies = [];

        // Reverse map meta fields
        foreach (self::FIELD_MAP as $houzez_key => $crm_path) {
            $value = $this->get_nested_value($crm_data, $crm_path);
            if ($value !== null) {
                $meta[$houzez_key] = $this->transform_value($houzez_key, $value, 'from_crm');
            }
        }

        // Reverse map taxonomies
        foreach (self::TAXONOMY_MAP as $taxonomy => $crm_path) {
            if ($crm_path === 'facilities' && isset($crm_data['facilities'])) {
                $taxonomies[$taxonomy] = $this->transform_taxonomy($taxonomy, $crm_data['facilities'], 'from_crm');
            } elseif (strpos($crm_path, 'address.') === 0) {
                $field = str_replace('address.', '', $crm_path);
                if (isset($crm_data['address'][$field])) {
                    $taxonomies[$taxonomy] = $this->transform_taxonomy($taxonomy, $crm_data['address'][$field], 'from_crm');
                }
            } elseif (isset($crm_data[$crm_path])) {
                $taxonomies[$taxonomy] = $this->transform_taxonomy($taxonomy, $crm_data[$crm_path], 'from_crm');
            }
        }

        // Handle coordinates
        if (isset($crm_data['address']['latitude']) && isset($crm_data['address']['longitude'])) {
            $meta['fave_property_location'] = $crm_data['address']['latitude'] . ',' . $crm_data['address']['longitude'];
        }

        return [
            'meta'       => $meta,
            'taxonomies' => $taxonomies,
        ];
    }

    /**
     * Transform a field value based on direction.
     *
     * @param string $field     Field key.
     * @param mixed  $value     Field value.
     * @param string $direction 'to_crm' or 'from_crm'.
     * @return mixed Transformed value.
     */
    private function transform_value($field, $value, $direction) {
        // Handle numeric fields
        $numeric_fields = [
            'fave_property_price',
            'fave_property_sec_price',
            'fave_property_size',
            'fave_property_bedrooms',
            'fave_property_bathrooms',
            'fave_property_rooms',
            'fave_property_garage',
            'fave_property_garage_size',
            'fave_property_year',
            'fave_property_land',
        ];

        if (in_array($field, $numeric_fields, true)) {
            if ($direction === 'to_crm') {
                // Convert to appropriate type for API
                if (in_array($field, ['fave_property_price', 'fave_property_sec_price', 'fave_property_size', 'fave_property_land', 'fave_property_garage_size'], true)) {
                    return (float) $value;
                }
                return (int) $value;
            } else {
                return (string) $value;
            }
        }

        // Handle boolean fields
        if ($field === 'fave_featured') {
            if ($direction === 'to_crm') {
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            } else {
                return $value ? '1' : '0';
            }
        }

        return $value;
    }

    /**
     * Transform taxonomy data.
     *
     * @param string $taxonomy  Taxonomy name.
     * @param mixed  $data      Taxonomy data.
     * @param string $direction 'to_crm' or 'from_crm'.
     * @return mixed Transformed data.
     */
    private function transform_taxonomy($taxonomy, $data, $direction) {
        if ($direction === 'to_crm') {
            // Array of term names/objects
            if (is_array($data)) {
                if ($taxonomy === 'property_feature') {
                    // Facilities - return array of objects with name
                    return array_map(function ($term) {
                        if (is_object($term)) {
                            return [
                                'uuid' => $this->get_term_crm_uuid($term->term_id) ?: null,
                                'name' => $term->name,
                            ];
                        }
                        return ['uuid' => null, 'name' => $term];
                    }, $data);
                } else {
                    // Single taxonomy - take first term
                    $term = reset($data);
                    if (is_object($term)) {
                        return [
                            'uuid' => $this->get_term_crm_uuid($term->term_id) ?: null,
                            'name' => $term->name,
                        ];
                    }
                    return ['uuid' => null, 'name' => $term];
                }
            }
        } else {
            // From CRM to Houzez
            if ($taxonomy === 'property_feature' && is_array($data)) {
                // Return array of term names for features
                return array_map(function ($item) {
                    return is_array($item) ? ($item['name'] ?? '') : $item;
                }, $data);
            }

            // Single taxonomy - extract name
            if (is_array($data) && isset($data['name'])) {
                return [$data['name']];
            }

            return is_array($data) ? $data : [$data];
        }

        return $data;
    }

    /**
     * Get a nested value from an array using dot notation.
     *
     * @param array  $array Array to search.
     * @param string $path  Dot-notation path.
     * @return mixed|null Value or null if not found.
     */
    private function get_nested_value($array, $path) {
        $keys = explode('.', $path);
        $value = $array;

        foreach ($keys as $key) {
            if (!is_array($value) || !isset($value[$key])) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }

    /**
     * Set a nested value in an array using dot notation.
     *
     * @param array  $array Array to modify.
     * @param string $path  Dot-notation path.
     * @param mixed  $value Value to set.
     */
    private function set_nested_value(&$array, $path, $value) {
        $keys = explode('.', $path);
        $current = &$array;

        foreach ($keys as $key) {
            if (!isset($current[$key]) || !is_array($current[$key])) {
                $current[$key] = [];
            }
            $current = &$current[$key];
        }

        $current = $value;
    }

    /**
     * Parse coordinates from Houzez format.
     *
     * @param string $location Location string (lat,lng).
     * @return array|null Array with 'lat' and 'lng' or null.
     */
    private function parse_coordinates($location) {
        if (empty($location)) {
            return null;
        }

        $parts = explode(',', $location);
        if (count($parts) !== 2) {
            return null;
        }

        $lat = (float) trim($parts[0]);
        $lng = (float) trim($parts[1]);

        if ($lat === 0.0 && $lng === 0.0) {
            return null;
        }

        return ['lat' => $lat, 'lng' => $lng];
    }

    /**
     * Get CRM UUID for a term.
     *
     * @param int $term_id WordPress term ID.
     * @return string|null CRM UUID or null.
     */
    private function get_term_crm_uuid($term_id) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table lookup
        $uuid = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT crm_uuid FROM {$wpdb->prefix}hcrm_entity_map WHERE entity_type = 'taxonomy' AND wp_id = %d",
                $term_id
            )
        );

        return $uuid ?: null;
    }

    /**
     * Get all supported Houzez meta fields.
     *
     * @return array List of meta field keys.
     */
    public function get_supported_meta_fields() {
        return array_keys(self::FIELD_MAP);
    }

    /**
     * Get all supported taxonomies.
     *
     * @return array List of taxonomy names.
     */
    public function get_supported_taxonomies() {
        return array_keys(self::TAXONOMY_MAP);
    }
}
