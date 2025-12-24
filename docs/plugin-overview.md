# Build a plugin to integrate to CRM

## Introduction

- The purpose of the plugin is to connect and sync from laravel CRM to Houzez theme.
- The sync will be two way. Plugin will sync through API and the CRM will sync through webhook.
- The plugin will be used to sync the properties(with all meta data and taxonomies and images), users(with roles), and leads.
- The Plugin will have a settings page with multiple tabs explained below.
- The plugin will have create a webhook page to receive the requests from the CRM.

## API Docs:

Here are the api documentations
Open API: https://houzez-crm.test/docs.openapi
Postman: https://houzez-crm.test/docs.postman

## Settings

1. API Setitngs
   API settings will be included below fields:
   After entering the base url and api token there should be a button to test the connection.
   Find the test API connection in authentication in API docs.

   - API Base URL
   - API token
   - Readonly webhook URL.

2. Sync Settings
   Sync settings will be included below fields:

   - Properties
   - Taxonomies
   - Users
   - Leads

**Important**

- The API should be well structured and class based structure.
- The UI should be modern and ajax based with proper error handing and alerts for success and error messages.
- Currently focus on listing API only.
