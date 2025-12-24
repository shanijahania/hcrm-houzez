# Lets upddate the plugin settings page

Currently there are two tabs

1. API settings
2. Sync Settings.

Lets break down the settings into more detailed tabs. The new tabls will be below

1. API Settings: (As current) and move the auto sync from Sync settings to API settings.
2. Properties Settings:

   ## Property post type

   - Will have option to sync properties
   - Show total properties in wordpress and total synced fromt he wp_hcrm_entity_map table.
   - Add action button to sync all properties including missing.

   ## Same with all property related taxonomies

   - Type
   - status
   - label
   - features

3. Users settings

   - User settings will include options to sync users. The section will show checkboxes of all available roles to sync with checkboxes. we only need to show below roles and map with CRM roles.
     - Manager
     - Agency
     - Agent
     - Administrator
   - The section will show the users already syned and pending users.
   - There will be a button to sync all users including user avatar.
   - For agency and agent use the custom post type houzez_agent and houzez_agency to sync and map.

4. Lead Settings.

   - The lead can be generated from multiple forms in the houzez theme. Will explain below.
   - Add option to sync leads from below hooks:
     - Elementor Inquiry Form = houzez_ele_inquiry_form
     - Elementor Contact Form = houzez_ele_contact_form
     - Agent and agency detail form = houzez_contact_realtor
     - Schedule Tour form = houzez_schedule_send_message
     - Property Detail Contact form = houzez_property_agent_contact
   - Add a separate wrapper class to sync all the leads.
   - Create a contact with each lead. Create contact type in CRM if not already created, Below users roles from wordpress will be treated as contact types:

     - Owner
     - Seller
     - Buyer

   - property detail lead should also link with the property in the CRM in listing_id field in leads table.

**Important**

- Make sure to prepare the CRM for all endpoints.
- All the data synced with CRM should be properly mapped in wordpress.
- Add a sync icon to all the data synced with CRM with option to sync manually.
  Each new property, taxonomy should be synced automatically with CRM if auto sync is ON through wordpress default create/update hooks.
