<?php
/**
 * Custom Fields Mapper class for mapping Houzez custom fields to CRM custom fields.
 *
 * @package HCRM_Houzez
 * @since   1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Class HCRM_Custom_Fields_Mapper
 *
 * Handles mapping between Houzez theme custom fields and CRM custom fields.
 *
 * @since 1.0.0
 */
class HCRM_Custom_Fields_Mapper {

    /**
     * Option key for storing the field mapping.
     */
    const MAPPING_OPTION = 'hcrm_custom_fields_mapping';

    /**
     * Get the current field mapping.
     *
     * @return array Array of field mappings.
     */
    public static function get_mapping() {
        $mapping = get_option( self::MAPPING_OPTION, [] );
        return is_array( $mapping ) ? $mapping : [];
    }

    /**
     * Save the field mapping.
     *
     * @param array $mapping Array of field mappings.
     * @return bool True on success, false on failure.
     */
    public static function save_mapping( $mapping ) {
        if ( ! is_array( $mapping ) ) {
            return false;
        }

        // Sanitize the mapping data.
        $sanitized = [];
        foreach ( $mapping as $item ) {
            if ( empty( $item['houzez_field_id'] ) || empty( $item['crm_slug'] ) ) {
                continue;
            }

            $sanitized[] = [
                'houzez_field_id' => sanitize_text_field( $item['houzez_field_id'] ),
                'houzez_label'    => sanitize_text_field( $item['houzez_label'] ?? '' ),
                'crm_slug'        => sanitize_text_field( $item['crm_slug'] ),
                'crm_label'       => sanitize_text_field( $item['crm_label'] ?? '' ),
            ];
        }

        return update_option( self::MAPPING_OPTION, $sanitized );
    }

    /**
     * Get Houzez custom fields from the fields builder table.
     *
     * @return array Array of Houzez custom fields.
     */
    public static function get_houzez_custom_fields() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'houzez_fields_builder';

        // Check if the table exists.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $table_exists = $wpdb->get_var(
            $wpdb->prepare(
                'SHOW TABLES LIKE %s',
                $table_name
            )
        );

        if ( ! $table_exists ) {
            return [];
        }

        // Get all custom fields from the Houzez fields builder table.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $fields = $wpdb->get_results(
            "SELECT id, label, field_id, type FROM {$table_name} ORDER BY id ASC",
            ARRAY_A
        );

        if ( empty( $fields ) ) {
            return [];
        }

        $result = [];
        foreach ( $fields as $field ) {
            $result[] = [
                'id'       => (int) $field['id'],
                'field_id' => $field['field_id'],
                'label'    => $field['label'],
                'type'     => $field['type'],
            ];
        }

        return $result;
    }

    /**
     * Map Houzez custom field values to CRM format for pushing to CRM.
     *
     * @param int $property_id WordPress property post ID.
     * @return array Array of custom fields in CRM format { slug: value }.
     */
    public static function map_houzez_to_crm( $property_id ) {
        $mapping = self::get_mapping();

        if ( empty( $mapping ) ) {
            return [];
        }

        $custom_fields = [];

        foreach ( $mapping as $map ) {
            $houzez_field_id = $map['houzez_field_id'];
            $crm_slug        = $map['crm_slug'];

            // Houzez stores custom field values with 'fave_' prefix.
            $meta_key = 'fave_' . $houzez_field_id;
            $value    = get_post_meta( $property_id, $meta_key, true );

            // Only include non-empty values.
            if ( '' !== $value && null !== $value ) {
                $custom_fields[ $crm_slug ] = $value;
            }
        }

        return $custom_fields;
    }

    /**
     * Map CRM custom field values to Houzez meta keys for pulling from CRM.
     *
     * @param array $crm_custom_fields CRM custom fields array { slug: value }.
     * @return array Array of meta keys and values { meta_key: value }.
     */
    public static function map_crm_to_houzez( $crm_custom_fields ) {
        if ( empty( $crm_custom_fields ) || ! is_array( $crm_custom_fields ) ) {
            return [];
        }

        $mapping = self::get_mapping();

        if ( empty( $mapping ) ) {
            return [];
        }

        // Create a lookup map from CRM slug to Houzez field_id.
        $slug_to_field_id = [];
        foreach ( $mapping as $map ) {
            $slug_to_field_id[ $map['crm_slug'] ] = $map['houzez_field_id'];
        }

        $meta_values = [];

        foreach ( $crm_custom_fields as $crm_slug => $value ) {
            if ( isset( $slug_to_field_id[ $crm_slug ] ) ) {
                $houzez_field_id = $slug_to_field_id[ $crm_slug ];
                $meta_key        = 'fave_' . $houzez_field_id;
                $meta_values[ $meta_key ] = $value;
            }
        }

        return $meta_values;
    }

    /**
     * Get the count of mapped fields.
     *
     * @return int Number of mapped fields.
     */
    public static function get_mapped_count() {
        return count( self::get_mapping() );
    }
}
