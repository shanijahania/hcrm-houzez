# Map the custom fields from houzez theme and CRM

## Instructions

- We have fixed fields in pos and postmeta table which are directly linked to CRM listings, listing_details and categories tables.

- now there are some custom fields in houzez theme in table houzez_fields_builder table and also there is a custom fields table in CRM tenant database custom_fields.

- For CRM the API is all ready where you can fetch thecustom fields by entity "listing" for our case.

- Now we need to create a mapping tool which pull the custom fields from CRM and also from the houzez to map them together and save into options table and then use the mapping to udpate the fields through webhook request from the CRM and API request from houzez theme.

- Add a button in the plugin property settings section for mapping which will open a modal which will show houzez custom fields and a dropdown to select CRM custom fields. Once mapped it will save the fields in options table.

- Once mapping is done The fields will be then used to update the fields through webhook request from the CRM and API request from houzez theme.

- In CRM we have custom fields added in tenant_royal database and a listing d86a95db-6c4f-4782-af5f-215335a6cd00 has already data in custom fields.

- The CRM API for listings already sending custom fields data in all listings responses like below.
  ```json
  "custom_fields": {
          "plot-number": "20",
          "property-tax-id": "ABC123"
      },
  ```
- The fields will be mapped with field_id in houzez and slug in the CRM custom fields table.

- The Custom fields API in CRM has "api/v1/custom-fields?entity_type=listing" endpoint with sample response below:

```json
{
  "success": true,
  "message": "Custom fields retrieved successfully",
  "data": [
    {
      "id": 1,
      "uuid": "edd748b6-0586-45a3-997c-9ee09c24e7ec",
      "name": "Property Tax ID",
      "slug": "property-tax-id",
      "placeholder": "Enter govt property tax ID",
      "helpText": null,
      "entityType": "listing",
      "section": "details",
      "fieldType": "text",
      "fieldOptions": null,
      "isRequired": false,
      "sortOrder": 0,
      "config": null,
      "createdAt": "2025-12-26T06:10:57.000000Z",
      "updatedAt": "2025-12-26T06:10:57.000000Z"
    },
    {
      "id": 2,
      "uuid": "b04735d3-edfc-4744-b3eb-75950a6d98d9",
      "name": "plot number",
      "slug": "plot-number",
      "placeholder": "Enter the society plot number",
      "helpText": null,
      "entityType": "listing",
      "section": "details",
      "fieldType": "number",
      "fieldOptions": null,
      "isRequired": false,
      "sortOrder": 0,
      "config": null,
      "createdAt": "2025-12-26T06:11:18.000000Z",
      "updatedAt": "2025-12-26T06:11:40.000000Z"
    }
  ]
}
```
