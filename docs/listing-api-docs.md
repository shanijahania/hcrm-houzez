# Listing API Integration Plan

**Version:** 1.0
**Last Updated:** 2025-12-20

## 1. Overview

This document provides comprehensive guidance for integrating your WordPress donor website with the Houzez CRM Laravel application to sync property listings.

### Purpose

Synchronize property listings between your WordPress donor site and the Laravel-based Houzez CRM, enabling real-time data flow and centralized property management.

### Key Features

-   **Bi-directional Sync**: Create and update listings from your WordPress site
-   **Entity Auto-Creation**: Automatically create related entities (agencies, contacts, users) if they don't exist
-   **UUID-based Identification**: Use UUIDs for reliable cross-system entity identification
-   **Backward Compatible**: Supports both UUID-based and legacy ID-based formats
-   **Asynchronous Image Processing**: Images are downloaded and processed in the background

### Authentication

-   **Method**: Laravel Sanctum token-based authentication
-   **Content-Type**: `application/json`
-   **Base URL**: `https://your-crm-domain.test/api/v1` (use production domain in production)

---

## 2. Authentication

### Obtaining an API Token

API tokens are managed through the CRM's settings interface.

1. Log into the CRM as an admin user
2. Navigate to **Settings → API Integrations**
3. Click **Generate New Token**
4. Provide a descriptive name (e.g., "WordPress Donor Site")
5. Select appropriate permissions (minimum: `listings:write`, `listings:read`)
6. Copy and securely store the generated token

**Important:** Store the token securely. It will only be displayed once.

### Using the Token

Include the token in the `Authorization` header with every API request:

```
Authorization: Bearer {your-api-token-here}
```

### Example Request Headers

```http
POST /api/v1/listings HTTP/1.1
Host: your-crm-domain.test
Content-Type: application/json
Accept: application/json
Authorization: Bearer 1|abcdefghijklmnopqrstuvwxyz1234567890
```

---

## 3. Endpoints

### 3.1 Create Listing

**Endpoint:** `POST /api/v1/listings`

**Description:** Create a new property listing in the CRM.

**Request Body:** See section 3.5 for full example with all fields.

### 3.2 Update Listing

**Endpoint:** `PUT /api/v1/listings/{uuid}`

**Description:** Update an existing property listing. Partial updates are supported - only send fields that have changed.

**Path Parameters:**

-   `{uuid}` (string, required): The UUID of the listing to update

**Example Request:**

```json
{
    "title": "Updated Villa Title",
    "price": 5500000,
    "detail": {
        "bedrooms": 6
    }
}
```

### 3.3 Get Listing

**Endpoint:** `GET /api/v1/listings/{uuid}`

**Description:** Retrieve a single listing by UUID.

**Path Parameters:**

-   `{uuid}` (string, required): The UUID of the listing

**Query Parameters:**

-   `include` (string, optional): Comma-separated list of relationships to include (e.g., `agency,owner_contact,assignees,detail,address`)

**Example:**

```
GET /api/v1/listings/550e8400-e29b-41d4-a716-446655440000?include=agency,assignees,detail
```

### 3.4 Search Listings

**Endpoint:** `GET /api/v1/listings/search`

**Description:** Search and filter listings.

**Query Parameters:**

-   `listing_id` (string): External listing ID from donor site
-   `status` (string): Status name
-   `agency` (string): Agency name or UUID
-   `listing_type` (string): Type name
-   `city` (string): City name
-   `min_price` (number): Minimum price
-   `max_price` (number): Maximum price
-   `bedrooms` (number): Number of bedrooms
-   `page` (number): Page number for pagination (default: 1)
-   `per_page` (number): Results per page (default: 15, max: 100)

**Example:**

```
GET /api/v1/listings/search?city=Dubai&min_price=1000000&bedrooms=3&page=1&per_page=20
```

### 3.5 Complete Request Example

**POST /api/v1/listings - Full Request Body:**

```json
{
    "title": "Luxury Villa in Palm Jumeirah",
    "slug": "luxury-villa-palm-jumeirah",
    "listing_id": "wp-12345",

    "status": {
        "uuid": "550e8400-e29b-41d4-a716-446655440000",
        "name": "For Sale"
    },

    "listing_type": {
        "uuid": null,
        "name": "Villa"
    },

    "listing_label": {
        "uuid": null,
        "name": "Hot Deal"
    },

    "agency": {
        "uuid": null,
        "name": "Premium Properties LLC",
        "slug": "premium-properties",
        "logo_url": "https://donor-site.com/logos/agency.png"
    },

    "owner_contact": {
        "uuid": null,
        "first_name": "Ahmed",
        "last_name": "Khan",
        "email": "ahmed@example.com",
        "phone": "+971501234567"
    },

    "created_by": {
        "uuid": null,
        "name": "John Admin",
        "email": "john@agency.com",
        "role": "agent"
    },

    "assignees": [
        {
            "uuid": null,
            "name": "Sarah Agent",
            "email": "sarah@agency.com",
            "avatar": "https://donor-site.com/avatars/sarah.jpg",
            "is_primary": true,
            "role": "agent"
        },
        {
            "uuid": null,
            "name": "Mike Agent",
            "email": "mike@agency.com",
            "is_primary": false,
            "role": "agent"
        }
    ],

    "price": 5000000,
    "second_price": 4800000,
    "price_prefix": "Starting from",
    "price_postfix": "",
    "currency_code": "AED",

    "published_at": "2024-01-15T10:00:00Z",
    "expires_at": "2024-06-15T10:00:00Z",
    "is_featured": true,

    "detail": {
        "description": "<p>Beautiful luxury villa with stunning views of the Arabian Gulf. This property features modern architecture, high-end finishes, and spacious living areas perfect for families.</p>",
        "bedrooms": 5,
        "bathrooms": 6,
        "parking": 4,
        "area_size": 8500,
        "area_unit": "sqft",
        "year_built": 2020,
        "floor_number": 0,
        "total_floors": 3,
        "land_area": 12000,
        "land_unit": "sqft",
        "garage": 2,
        "garage_size": 400,
        "garage_unit": "sqft"
    },

    "address": {
        "country": {
            "uuid": null,
            "name": "United Arab Emirates",
            "iso2": "AE",
            "iso3": "ARE"
        },
        "state": {
            "uuid": null,
            "name": "Dubai"
        },
        "city": {
            "uuid": null,
            "name": "Dubai"
        },
        "area": {
            "uuid": null,
            "name": "Palm Jumeirah"
        },
        "address_line1": "Frond A, Villa 15",
        "address_line2": "Palm Jumeirah",
        "latitude": 25.1124,
        "longitude": 55.1391
    },

    "amenities": [
        { "uuid": null, "name": "Swimming Pool" },
        { "uuid": null, "name": "Private Beach" },
        { "uuid": null, "name": "Gym" },
        { "uuid": null, "name": "Sauna" },
        { "uuid": null, "name": "24/7 Security" }
    ],

    "facilities": [
        { "uuid": null, "name": "Metro Station", "distance": "5 km" },
        { "uuid": null, "name": "International School", "distance": "2 km" },
        { "uuid": null, "name": "Shopping Mall", "distance": "3 km" },
        { "uuid": null, "name": "Hospital", "distance": "4 km" }
    ],

    "floor_plans": [
        {
            "uuid": null,
            "plan_title": "Ground Floor",
            "plan_description": "Living area, dining room, kitchen, and guest bedroom",
            "bedrooms": 1,
            "bathrooms": 2,
            "area_size": 2500,
            "area_unit": "sqft",
            "image_url": "https://donor-site.com/floorplans/ground-floor.jpg"
        },
        {
            "uuid": null,
            "plan_title": "First Floor",
            "plan_description": "Master suite and two guest bedrooms",
            "bedrooms": 3,
            "bathrooms": 3,
            "area_size": 3000,
            "area_unit": "sqft",
            "image_url": "https://donor-site.com/floorplans/first-floor.jpg"
        },
        {
            "uuid": null,
            "plan_title": "Second Floor",
            "plan_description": "Entertainment area and additional bedroom",
            "bedrooms": 1,
            "bathrooms": 1,
            "area_size": 2000,
            "area_unit": "sqft",
            "image_url": "https://donor-site.com/floorplans/second-floor.jpg"
        }
    ],

    "images": [
        {
            "url": "https://donor-site.com/images/villa-front-view.jpg",
            "order": 1,
            "name": "Front View"
        },
        {
            "url": "https://donor-site.com/images/villa-pool.jpg",
            "order": 2,
            "name": "Pool Area"
        },
        {
            "url": "https://donor-site.com/images/villa-living-room.jpg",
            "order": 3,
            "name": "Living Room"
        },
        {
            "url": "https://donor-site.com/images/villa-kitchen.jpg",
            "order": 4,
            "name": "Modern Kitchen"
        },
        {
            "url": "https://donor-site.com/images/villa-master-bedroom.jpg",
            "order": 5,
            "name": "Master Bedroom"
        }
    ],

    "custom_fields": [
        { "key": "property_id", "value": "PJ-12345" },
        {
            "key": "virtual_tour",
            "value": "https://tour.example.com/villa-palm-jumeirah"
        },
        { "key": "video_url", "value": "https://youtube.com/watch?v=example" },
        { "key": "permit_number", "value": "DLD-2024-12345" }
    ]
}
```

---

## 4. Response Format

### 4.1 Success Response

**HTTP Status:** `200 OK` (for GET) or `201 Created` (for POST)

```json
{
    "success": true,
    "message": "Listing synced successfully",
    "data": {
        "id": 123,
        "uuid": "9c7f8d5e-3b2a-4c1e-8f9d-2a3b4c5d6e7f",
        "title": "Luxury Villa in Palm Jumeirah",
        "slug": "luxury-villa-palm-jumeirah",
        "listing_id": "wp-12345",

        "status": {
            "id": 1,
            "uuid": "550e8400-e29b-41d4-a716-446655440000",
            "name": "For Sale"
        },

        "listing_type": {
            "id": 2,
            "uuid": "8c6e7d4f-2a1b-3c0d-7e8f-1a2b3c4d5e6f",
            "name": "Villa"
        },

        "listing_label": {
            "id": 3,
            "uuid": "7b5c6d3e-1a0b-2c9d-6e7f-0a1b2c3d4e5f",
            "name": "Hot Deal"
        },

        "agency": {
            "id": 5,
            "uuid": "6a4b5c2d-0a9b-1c8d-5e6f-9a0b1c2d3e4f",
            "name": "Premium Properties LLC",
            "slug": "premium-properties"
        },

        "owner_contact": {
            "id": 42,
            "uuid": "5a3b4c1d-9a8b-0c7d-4e5f-8a9b0c1d2e3f",
            "first_name": "Ahmed",
            "last_name": "Khan",
            "email": "ahmed@example.com",
            "phone": "+971501234567"
        },

        "created_by": {
            "id": 15,
            "uuid": "4a2b3c0d-8a7b-9c6d-3e4f-7a8b9c0d1e2f",
            "name": "John Admin",
            "email": "john@agency.com"
        },

        "assignees": [
            {
                "id": 16,
                "uuid": "3a1b2c9d-7a6b-8c5d-2e3f-6a7b8c9d0e1f",
                "name": "Sarah Agent",
                "email": "sarah@agency.com",
                "is_primary": true
            },
            {
                "id": 17,
                "uuid": "2a0b1c8d-6a5b-7c4d-1e2f-5a6b7c8d9e0f",
                "name": "Mike Agent",
                "email": "mike@agency.com",
                "is_primary": false
            }
        ],

        "price": 5000000,
        "second_price": 4800000,
        "currency_code": "AED",
        "published_at": "2024-01-15T10:00:00+00:00",
        "expires_at": "2024-06-15T10:00:00+00:00",
        "is_featured": true,

        "detail": {
            "id": 123,
            "bedrooms": 5,
            "bathrooms": 6,
            "area_size": 8500,
            "area_unit": "sqft"
        },

        "address": {
            "id": 123,
            "country_id": 1,
            "state_id": 5,
            "city_id": 12,
            "area_id": 48,
            "address_line1": "Frond A, Villa 15",
            "latitude": 25.1124,
            "longitude": 55.1391
        },

        "created_at": "2024-01-15T09:30:00+00:00",
        "updated_at": "2024-01-15T09:30:00+00:00"
    }
}
```

**Critical: Store UUIDs for Future Operations**

The response contains UUIDs for all created or resolved entities. You **must** store these UUIDs in your WordPress database for future sync operations:

-   `data.uuid` - The listing UUID
-   `data.status.uuid` - Status UUID
-   `data.listing_type.uuid` - Listing type UUID
-   `data.listing_label.uuid` - Label UUID
-   `data.agency.uuid` - Agency UUID
-   `data.owner_contact.uuid` - Owner contact UUID
-   `data.created_by.uuid` - User UUID
-   `data.assignees[].uuid` - Each assignee's UUID

### 4.2 Error Response

**HTTP Status:** `400 Bad Request`, `401 Unauthorized`, `403 Forbidden`, `404 Not Found`, `422 Unprocessable Entity`, or `500 Internal Server Error`

```json
{
    "success": false,
    "message": "Validation failed",
    "errors": {
        "title": ["The title field is required."],
        "assignees.0.email": ["The email field is required."],
        "price": [
            "The price must be a number.",
            "The price must be at least 0."
        ],
        "detail.bedrooms": ["The bedrooms must be an integer."]
    }
}
```

### 4.3 Common HTTP Status Codes

| Status Code                 | Meaning          | Description                               |
| --------------------------- | ---------------- | ----------------------------------------- |
| `200 OK`                    | Success          | Request completed successfully (GET, PUT) |
| `201 Created`               | Created          | Resource created successfully (POST)      |
| `400 Bad Request`           | Client Error     | Malformed request body or invalid JSON    |
| `401 Unauthorized`          | Auth Error       | Missing or invalid authentication token   |
| `403 Forbidden`             | Permission Error | Token lacks required permissions          |
| `404 Not Found`             | Not Found        | Requested resource doesn't exist          |
| `422 Unprocessable Entity`  | Validation Error | Request validation failed                 |
| `429 Too Many Requests`     | Rate Limit       | Too many requests, slow down              |
| `500 Internal Server Error` | Server Error     | Unexpected server error occurred          |

---

## 5. Entity Resolution Logic

The API intelligently resolves related entities (agencies, contacts, users, etc.) using a smart lookup-or-create strategy.

### Resolution Process

For each entity, the API follows this logic:

1. **UUID Match**: If a `uuid` is provided and matches an existing record, use that record
2. **Lookup by Attributes**: If `uuid` is `null` or not found, search for existing record by key attributes (name, email, etc.)
3. **Create New**: If no existing record is found, create a new one with the provided data

### Entity-Specific Resolution

#### Status, Listing Type, Listing Label

-   **Lookup Key**: `name` (case-insensitive)
-   **Auto-Create**: Yes, if `name` is provided

```json
{
    "status": {
        "uuid": null,
        "name": "For Sale"
    }
}
```

Result: Finds status with name "For Sale" or creates it.

#### Agency

-   **Lookup Keys**: `uuid` → `name` → `slug`
-   **Auto-Create**: Yes, with provided details
-   **Additional Fields**: `logo_url` (downloaded asynchronously)

```json
{
    "agency": {
        "uuid": null,
        "name": "Premium Properties",
        "slug": "premium-properties",
        "logo_url": "https://example.com/logo.png"
    }
}
```

#### Owner Contact

-   **Lookup Keys**: `uuid` → `email`
-   **Auto-Create**: Yes, if `email` is provided
-   **Required Field**: `email`

```json
{
    "owner_contact": {
        "uuid": null,
        "email": "owner@example.com",
        "first_name": "Ahmed",
        "last_name": "Khan"
    }
}
```

#### Created By (User)

-   **Lookup Keys**: `uuid` → `email`
-   **Auto-Create**: Yes, if `email` and `name` are provided
-   **Required Fields**: `email`, `name`
-   **Default Role**: Set by `role` field or defaults to "agent"

```json
{
    "created_by": {
        "uuid": null,
        "email": "john@agency.com",
        "name": "John Admin",
        "role": "agent"
    }
}
```

#### Assignees (Users)

-   **Lookup Keys**: `uuid` → `email`
-   **Auto-Create**: Yes, if `email` and `name` are provided
-   **Required Fields**: `email`, `name`
-   **Special Field**: `is_primary` (boolean, marks primary assignee)

```json
{
    "assignees": [
        {
            "uuid": null,
            "email": "sarah@agency.com",
            "name": "Sarah Agent",
            "is_primary": true
        }
    ]
}
```

#### Location Entities (Country, State, City, Area)

-   **Lookup Key**: `name` (case-insensitive)
-   **Auto-Create**: Yes
-   **Hierarchical**: Country → State → City → Area

```json
{
    "address": {
        "country": { "name": "United Arab Emirates" },
        "state": { "name": "Dubai" },
        "city": { "name": "Dubai" },
        "area": { "name": "Palm Jumeirah" }
    }
}
```

#### Amenities and Facilities

-   **Lookup Key**: `name` (case-insensitive)
-   **Auto-Create**: Yes
-   **Note**: Existing relationships are replaced (not merged)

```json
{
    "amenities": [
        { "uuid": null, "name": "Swimming Pool" },
        { "uuid": null, "name": "Gym" }
    ]
}
```

### Best Practice: Always Store UUIDs

After the first successful creation, **always use UUIDs** for subsequent updates to:

-   Avoid duplicate entity creation
-   Improve performance (UUID lookup is faster)
-   Prevent name-based conflicts
-   Ensure reliable entity references

---

## 6. Backward Compatibility

The API maintains full backward compatibility with the legacy ID-based format. You can use either format or mix both.

### Legacy ID-Based Format

```json
{
    "title": "Test Listing",
    "status_id": 1,
    "listing_type_id": 2,
    "listing_label_id": 3,
    "agency_id": 5,
    "owner_contact_id": 42,
    "created_by_id": 15,
    "price": 1000000
}
```

### Mixed Format (Allowed)

```json
{
    "title": "Mixed Format Listing",
    "status_id": 1,
    "agency": {
        "uuid": "6a4b5c2d-0a9b-1c8d-5e6f-9a0b1c2d3e4f",
        "name": "Premium Properties"
    },
    "owner_contact": {
        "uuid": null,
        "email": "owner@example.com",
        "first_name": "Ahmed"
    }
}
```

### Priority Rules

When both formats are provided:

1. `status` object takes precedence over `status_id`
2. `agency` object takes precedence over `agency_id`
3. And so on for all entities

### Migration Path

**Recommended approach:**

1. **Initial Sync**: Use name-based format (UUIDs as `null`)
2. **Store UUIDs**: Save all UUIDs returned in the response
3. **Future Syncs**: Use UUID-based format for better performance
4. **Gradual Migration**: Update your WordPress database over time

---

## 7. Image Handling

### Overview

Images (listing photos and floor plan images) are processed asynchronously to prevent timeouts on large image sets.

### How It Works

1. You provide publicly accessible image URLs in your request
2. The API validates the URLs and queues download jobs
3. The response returns immediately (no waiting for downloads)
4. Images are downloaded and processed in the background (usually within 10-30 seconds)
5. Failed downloads are logged but don't block the listing creation

### Listing Images Format

```json
{
    "images": [
        {
            "url": "https://donor-site.com/images/villa-1.jpg",
            "order": 1,
            "name": "Front View"
        },
        {
            "url": "https://donor-site.com/images/villa-2.jpg",
            "order": 2,
            "name": "Pool Area"
        }
    ]
}
```

**Fields:**

-   `url` (string, required): Publicly accessible image URL
-   `order` (integer, optional): Display order (defaults to array index)
-   `name` (string, optional): Image caption/name

### Floor Plan Images Format

```json
{
    "floor_plans": [
        {
            "plan_title": "Ground Floor",
            "image_url": "https://donor-site.com/floorplans/ground.jpg",
            "bedrooms": 1,
            "bathrooms": 2
        }
    ]
}
```

**Fields:**

-   `image_url` (string, optional): Publicly accessible floor plan image URL

### Image URL Requirements

-   Must be **publicly accessible** (no authentication required)
-   Supported formats: JPG, JPEG, PNG, GIF, WEBP
-   Recommended maximum size: 10MB per image
-   Must use HTTPS protocol (recommended)
-   Must return proper `Content-Type` header

### Agency Logo Handling

```json
{
    "agency": {
        "name": "Premium Properties",
        "logo_url": "https://donor-site.com/logos/agency.png"
    }
}
```

Agency logos are also downloaded asynchronously.

### Timing Expectations

-   **API Response**: Immediate (< 1 second)
-   **Image Download**: 10-30 seconds for typical image sets
-   **Large Sets**: May take up to 2 minutes for 20+ images

### Error Handling

-   Individual image download failures don't block listing creation
-   Failed downloads are logged server-side
-   To verify successful downloads, fetch the listing after 1 minute

### Best Practices

1. **Use CDN URLs**: Faster downloads, better reliability
2. **Optimize Images**: Compress before uploading to donor site
3. **Test URLs**: Ensure URLs are publicly accessible before syncing
4. **Verify Downloads**: Check listing images after sync completes
5. **Retry Failed Downloads**: Re-sync the listing if images fail

---

## 8. Best Practices

### 8.1 UUID Management

-   **Always store UUIDs** returned in API responses
-   Create a mapping table in WordPress:
    ```sql
    wp_crm_entity_map
    - entity_type (listing, agency, contact, user, etc.)
    - wp_id (WordPress post/user/term ID)
    - crm_uuid (UUID from CRM)
    - synced_at (timestamp)
    ```

### 8.2 Error Handling

-   **Handle 4xx errors gracefully**: These indicate client-side issues (validation, authentication)
-   **Retry 5xx errors**: Server errors may be temporary, retry with exponential backoff
-   **Log all errors**: Keep detailed logs for debugging sync issues
-   **Validate before sending**: Pre-validate data to reduce API errors

### 8.3 Rate Limiting

-   **Respect rate limits**: Current limit is 60 requests/minute per token
-   **Batch operations**: Group related updates when possible
-   **Handle 429 responses**: Back off and retry after the specified delay
-   **Use webhooks**: For real-time updates (when available)

### 8.4 Performance Optimization

-   **Only send changed fields**: Use partial updates (PUT with only modified fields)
-   **Use UUIDs**: Much faster than name-based lookups
-   **Paginate search results**: Don't fetch all listings at once
-   **Cache responses**: Reduce unnecessary API calls

### 8.5 Data Validation

-   **Validate required fields** before sending:
    -   `title` (required)
    -   At least one of: `status` object or `status_id`
    -   Valid email format for contacts and users
    -   Valid currency codes (ISO 4217)
    -   Valid date formats (ISO 8601)

### 8.6 Image Handling

-   **Test image URLs** before syncing
-   **Use absolute URLs** (not relative paths)
-   **Optimize images** before uploading (compress, resize)
-   **Provide valid Content-Type** headers on your server

### 8.7 Testing Strategy

1. **Test in staging first**: Use a test CRM instance
2. **Start with minimal data**: Test with bare minimum required fields
3. **Gradually add complexity**: Add optional fields incrementally
4. **Test error scenarios**: Invalid data, missing required fields, etc.
5. **Verify entity creation**: Check that agencies, contacts are created correctly

### 8.8 Monitoring & Logging

-   **Log all API requests** with request/response bodies
-   **Monitor sync failures**: Set up alerts for repeated failures
-   **Track sync performance**: Measure time taken for different operations
-   **Audit trail**: Keep history of when listings were synced

### 8.9 Security

-   **Store tokens securely**: Use WordPress options with encryption
-   **Use HTTPS only**: Never send tokens over HTTP
-   **Rotate tokens regularly**: Generate new tokens every 90 days
-   **Limit token permissions**: Only grant necessary permissions
-   **Validate responses**: Don't trust API responses blindly

---

## 9. Code Examples

### 9.1 PHP (WordPress) Example

```php
<?php

/**
 * Sync a property listing to CRM
 */
function sync_listing_to_crm($post_id) {
    // Get API credentials from WordPress options
    $api_url = get_option('crm_api_url'); // e.g., https://crm.example.com/api/v1
    $api_token = get_option('crm_api_token');

    // Get WordPress post data
    $post = get_post($post_id);
    $listing_uuid = get_post_meta($post_id, '_crm_uuid', true);

    // Build listing data
    $listing_data = [
        'title' => $post->post_title,
        'slug' => $post->post_name,
        'listing_id' => 'wp-' . $post_id,
        'price' => (float) get_post_meta($post_id, 'price', true),
        'currency_code' => get_post_meta($post_id, 'currency', true) ?: 'AED',
        'is_featured' => get_post_meta($post_id, 'featured', true) === '1',

        'status' => [
            'uuid' => get_term_meta(get_post_meta($post_id, 'status_id', true), '_crm_uuid', true) ?: null,
            'name' => get_term_field('name', get_post_meta($post_id, 'status_id', true))
        ],

        'listing_type' => [
            'uuid' => get_term_meta(get_post_meta($post_id, 'type_id', true), '_crm_uuid', true) ?: null,
            'name' => get_term_field('name', get_post_meta($post_id, 'type_id', true))
        ],

        'agency' => [
            'uuid' => get_post_meta(get_post_meta($post_id, 'agency_id', true), '_crm_uuid', true) ?: null,
            'name' => get_the_title(get_post_meta($post_id, 'agency_id', true)),
            'logo_url' => get_the_post_thumbnail_url(get_post_meta($post_id, 'agency_id', true))
        ],

        'detail' => [
            'description' => $post->post_content,
            'bedrooms' => (int) get_post_meta($post_id, 'bedrooms', true),
            'bathrooms' => (int) get_post_meta($post_id, 'bathrooms', true),
            'area_size' => (float) get_post_meta($post_id, 'area_size', true),
            'area_unit' => get_post_meta($post_id, 'area_unit', true) ?: 'sqft',
        ],

        'address' => [
            'country' => ['name' => get_post_meta($post_id, 'country', true)],
            'city' => ['name' => get_post_meta($post_id, 'city', true)],
            'address_line1' => get_post_meta($post_id, 'address', true),
            'latitude' => (float) get_post_meta($post_id, 'lat', true),
            'longitude' => (float) get_post_meta($post_id, 'lng', true),
        ],

        'images' => get_listing_images($post_id),
    ];

    // Determine if update or create
    $method = $listing_uuid ? 'PUT' : 'POST';
    $endpoint = $listing_uuid
        ? "/listings/{$listing_uuid}"
        : '/listings';

    // Make API request
    $response = wp_remote_request($api_url . $endpoint, [
        'method' => $method,
        'headers' => [
            'Authorization' => 'Bearer ' . $api_token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ],
        'body' => json_encode($listing_data),
        'timeout' => 30,
    ]);

    // Handle response
    if (is_wp_error($response)) {
        error_log('CRM Sync Error: ' . $response->get_error_message());
        return false;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    if ($status_code >= 200 && $status_code < 300) {
        // Success - store UUIDs
        update_post_meta($post_id, '_crm_uuid', $body['data']['uuid']);
        update_post_meta($post_id, '_crm_synced_at', current_time('mysql'));

        // Store related entity UUIDs
        if (!empty($body['data']['status']['uuid'])) {
            update_term_meta(
                get_post_meta($post_id, 'status_id', true),
                '_crm_uuid',
                $body['data']['status']['uuid']
            );
        }

        if (!empty($body['data']['agency']['uuid'])) {
            update_post_meta(
                get_post_meta($post_id, 'agency_id', true),
                '_crm_uuid',
                $body['data']['agency']['uuid']
            );
        }

        return true;
    } else {
        // Error - log details
        error_log(sprintf(
            'CRM Sync Error [%d]: %s - Errors: %s',
            $status_code,
            $body['message'] ?? 'Unknown error',
            json_encode($body['errors'] ?? [])
        ));

        return false;
    }
}

/**
 * Get listing images formatted for CRM API
 */
function get_listing_images($post_id) {
    $images = [];
    $gallery = get_post_meta($post_id, 'gallery', true);

    if (!empty($gallery)) {
        $attachment_ids = explode(',', $gallery);
        $order = 1;

        foreach ($attachment_ids as $attachment_id) {
            $images[] = [
                'url' => wp_get_attachment_url($attachment_id),
                'order' => $order++,
                'name' => get_the_title($attachment_id),
            ];
        }
    }

    return $images;
}

/**
 * Hook into post save
 */
add_action('save_post_property', function($post_id) {
    // Avoid autosaves and revisions
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;

    // Only sync published listings
    if (get_post_status($post_id) !== 'publish') return;

    // Sync to CRM
    sync_listing_to_crm($post_id);
}, 20);
```

### 9.2 JavaScript (Node.js) Example

```javascript
const axios = require("axios");

class CRMClient {
    constructor(apiUrl, apiToken) {
        this.apiUrl = apiUrl;
        this.apiToken = apiToken;

        this.client = axios.create({
            baseURL: apiUrl,
            headers: {
                Authorization: `Bearer ${apiToken}`,
                "Content-Type": "application/json",
                Accept: "application/json",
            },
            timeout: 30000,
        });
    }

    async createListing(listingData) {
        try {
            const response = await this.client.post("/listings", listingData);
            return { success: true, data: response.data };
        } catch (error) {
            return this.handleError(error);
        }
    }

    async updateListing(uuid, listingData) {
        try {
            const response = await this.client.put(
                `/listings/${uuid}`,
                listingData
            );
            return { success: true, data: response.data };
        } catch (error) {
            return this.handleError(error);
        }
    }

    async getListing(uuid, include = []) {
        try {
            const params = include.length ? { include: include.join(",") } : {};
            const response = await this.client.get(`/listings/${uuid}`, {
                params,
            });
            return { success: true, data: response.data };
        } catch (error) {
            return this.handleError(error);
        }
    }

    async searchListings(filters = {}, page = 1, perPage = 15) {
        try {
            const response = await this.client.get("/listings/search", {
                params: { ...filters, page, per_page: perPage },
            });
            return { success: true, data: response.data };
        } catch (error) {
            return this.handleError(error);
        }
    }

    handleError(error) {
        if (error.response) {
            // Server responded with error status
            return {
                success: false,
                status: error.response.status,
                message: error.response.data.message || "Unknown error",
                errors: error.response.data.errors || {},
            };
        } else if (error.request) {
            // Request made but no response
            return {
                success: false,
                message: "No response from server",
                error: error.message,
            };
        } else {
            // Error setting up request
            return {
                success: false,
                message: "Request setup error",
                error: error.message,
            };
        }
    }
}

// Usage example
async function syncListing() {
    const crm = new CRMClient(
        "https://crm.example.com/api/v1",
        "your-api-token-here"
    );

    const listing = {
        title: "Luxury Villa in Palm Jumeirah",
        slug: "luxury-villa-palm-jumeirah",
        listing_id: "wp-12345",
        price: 5000000,
        currency_code: "AED",

        status: {
            uuid: null,
            name: "For Sale",
        },

        agency: {
            uuid: null,
            name: "Premium Properties LLC",
        },

        detail: {
            bedrooms: 5,
            bathrooms: 6,
            area_size: 8500,
            area_unit: "sqft",
        },

        address: {
            country: { name: "United Arab Emirates" },
            city: { name: "Dubai" },
        },
    };

    const result = await crm.createListing(listing);

    if (result.success) {
        console.log("Listing synced successfully!");
        console.log("UUID:", result.data.data.uuid);

        // Store the UUID for future updates
        // saveToDatabase(result.data.data.uuid);
    } else {
        console.error("Sync failed:", result.message);
        console.error("Errors:", result.errors);
    }
}
```

### 9.3 Python Example

```python
import requests
from typing import Dict, Optional, List

class CRMClient:
    def __init__(self, api_url: str, api_token: str):
        self.api_url = api_url.rstrip('/')
        self.api_token = api_token
        self.session = requests.Session()
        self.session.headers.update({
            'Authorization': f'Bearer {api_token}',
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        })

    def create_listing(self, listing_data: Dict) -> Dict:
        try:
            response = self.session.post(
                f'{self.api_url}/listings',
                json=listing_data,
                timeout=30
            )
            response.raise_for_status()
            return {'success': True, 'data': response.json()}
        except requests.exceptions.RequestException as e:
            return self._handle_error(e)

    def update_listing(self, uuid: str, listing_data: Dict) -> Dict:
        try:
            response = self.session.put(
                f'{self.api_url}/listings/{uuid}',
                json=listing_data,
                timeout=30
            )
            response.raise_for_status()
            return {'success': True, 'data': response.json()}
        except requests.exceptions.RequestException as e:
            return self._handle_error(e)

    def get_listing(self, uuid: str, include: Optional[List[str]] = None) -> Dict:
        try:
            params = {'include': ','.join(include)} if include else {}
            response = self.session.get(
                f'{self.api_url}/listings/{uuid}',
                params=params,
                timeout=30
            )
            response.raise_for_status()
            return {'success': True, 'data': response.json()}
        except requests.exceptions.RequestException as e:
            return self._handle_error(e)

    def search_listings(self, filters: Optional[Dict] = None,
                       page: int = 1, per_page: int = 15) -> Dict:
        try:
            params = {**(filters or {}), 'page': page, 'per_page': per_page}
            response = self.session.get(
                f'{self.api_url}/listings/search',
                params=params,
                timeout=30
            )
            response.raise_for_status()
            return {'success': True, 'data': response.json()}
        except requests.exceptions.RequestException as e:
            return self._handle_error(e)

    def _handle_error(self, error: requests.exceptions.RequestException) -> Dict:
        if hasattr(error, 'response') and error.response is not None:
            try:
                data = error.response.json()
                return {
                    'success': False,
                    'status': error.response.status_code,
                    'message': data.get('message', 'Unknown error'),
                    'errors': data.get('errors', {})
                }
            except ValueError:
                return {
                    'success': False,
                    'status': error.response.status_code,
                    'message': error.response.text
                }
        else:
            return {
                'success': False,
                'message': str(error)
            }

# Usage example
if __name__ == '__main__':
    crm = CRMClient(
        api_url='https://crm.example.com/api/v1',
        api_token='your-api-token-here'
    )

    listing = {
        'title': 'Luxury Villa in Palm Jumeirah',
        'price': 5000000,
        'currency_code': 'AED',
        'status': {'uuid': None, 'name': 'For Sale'},
        'detail': {
            'bedrooms': 5,
            'bathrooms': 6,
            'area_size': 8500
        }
    }

    result = crm.create_listing(listing)

    if result['success']:
        print('Listing synced successfully!')
        print(f"UUID: {result['data']['data']['uuid']}")
    else:
        print(f"Sync failed: {result['message']}")
        print(f"Errors: {result.get('errors', {})}")
```

---

## 10. Changelog

### Version 1.0 (2025-12-20)

-   Initial release of Listing Sync API
-   Support for UUID-based entity resolution
-   Support for legacy ID-based format (backward compatibility)
-   Automatic entity creation (agencies, contacts, users, etc.)
-   Asynchronous image processing
-   Comprehensive entity relationships (assignees, amenities, facilities, floor plans)
-   Search and filter capabilities
-   Full CRUD operations (Create, Read, Update)

---

## 11. Support & Troubleshooting

### Common Issues

#### 1. Authentication Errors (401)

**Problem:** "Unauthenticated" or "Invalid token"

**Solutions:**

-   Verify token is correct and hasn't expired
-   Check `Authorization` header format: `Bearer {token}`
-   Ensure token has required permissions
-   Generate a new token if necessary

#### 2. Validation Errors (422)

**Problem:** "Validation failed"

**Solutions:**

-   Check the `errors` object in response for specific field issues
-   Ensure required fields are present (`title`, etc.)
-   Verify data types (numbers for price, integers for bedrooms, etc.)
-   Check email format for contacts/users
-   Ensure currency codes are valid ISO 4217 codes

#### 3. Images Not Appearing

**Problem:** Listing created but images missing

**Solutions:**

-   Wait 1-2 minutes for asynchronous processing
-   Verify image URLs are publicly accessible
-   Check image URLs return correct `Content-Type` headers
-   Test URLs in browser to ensure they work
-   Check CRM logs for download errors

#### 4. Duplicate Entities Created

**Problem:** New agencies/contacts created on every sync

**Solutions:**

-   Store and use UUIDs from first sync
-   Ensure email addresses match exactly for contacts/users
-   Check name matching is case-insensitive
-   Verify UUID lookup is working

#### 5. Rate Limit Errors (429)

**Problem:** "Too many requests"

**Solutions:**

-   Implement exponential backoff retry logic
-   Reduce sync frequency
-   Batch operations where possible
-   Use webhooks instead of polling (when available)

### Getting Help

For technical support or questions:

1. **Check API Logs**: Review your request/response logs
2. **Test in Staging**: Use a test CRM instance first
3. **Contact Support**: Provide request body, response, and error details
4. **API Documentation**: Refer to this document and inline API docs

---

## 12. Appendix

### A. Complete Field Reference

#### Listing Root Fields

| Field           | Type     | Required | Description                                        |
| --------------- | -------- | -------- | -------------------------------------------------- |
| `title`         | string   | Yes      | Property title                                     |
| `slug`          | string   | No       | URL-friendly slug (auto-generated if not provided) |
| `listing_id`    | string   | No       | External identifier from donor site                |
| `price`         | number   | No       | Primary price                                      |
| `second_price`  | number   | No       | Secondary/reduced price                            |
| `price_prefix`  | string   | No       | Text before price (e.g., "Starting from")          |
| `price_postfix` | string   | No       | Text after price                                   |
| `currency_code` | string   | No       | ISO 4217 currency code (default: AED)              |
| `published_at`  | datetime | No       | Publication date (ISO 8601 format)                 |
| `expires_at`    | datetime | No       | Expiration date (ISO 8601 format)                  |
| `is_featured`   | boolean  | No       | Featured listing flag                              |

#### Detail Fields

| Field          | Type      | Description                 |
| -------------- | --------- | --------------------------- |
| `description`  | text/html | Full property description   |
| `bedrooms`     | integer   | Number of bedrooms          |
| `bathrooms`    | integer   | Number of bathrooms         |
| `parking`      | integer   | Parking spaces              |
| `area_size`    | number    | Property area size          |
| `area_unit`    | string    | Area unit (sqft, sqm, etc.) |
| `year_built`   | integer   | Construction year           |
| `floor_number` | integer   | Floor number                |
| `total_floors` | integer   | Total building floors       |
| `land_area`    | number    | Land area size              |
| `land_unit`    | string    | Land area unit              |
| `garage`       | integer   | Number of garages           |
| `garage_size`  | number    | Garage size                 |
| `garage_unit`  | string    | Garage size unit            |

#### Address Fields

| Field           | Type   | Description            |
| --------------- | ------ | ---------------------- |
| `address_line1` | string | Primary address line   |
| `address_line2` | string | Secondary address line |
| `latitude`      | number | GPS latitude           |
| `longitude`     | number | GPS longitude          |

### B. ISO Standards Reference

**Currency Codes (ISO 4217):**

-   AED - United Arab Emirates Dirham
-   USD - US Dollar
-   EUR - Euro
-   GBP - British Pound
-   SAR - Saudi Riyal

**Date Format (ISO 8601):**

-   `2024-01-15T10:00:00Z` (UTC)
-   `2024-01-15T10:00:00+04:00` (with timezone)

### C. Glossary

-   **UUID**: Universally Unique Identifier - A 36-character string for identifying entities
-   **Sanctum**: Laravel's token-based authentication system
-   **Entity Resolution**: Process of finding or creating related records
-   **Asynchronous Processing**: Background jobs that run after API response
-   **Partial Update**: Sending only changed fields in an update request
-   **Tenant**: Organization/agency in a multi-tenant system

---

**End of Document**

For the most up-to-date information, please refer to the inline API documentation or contact your CRM administrator.
