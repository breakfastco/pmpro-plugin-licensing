# pmpro-plugin-licensing

**Plugin Licensing for Paid Memberships Pro**

WordPress plugin. Add-on for Paid Memberships Pro. Sell WordPress plugins &amp; provide updates.

## Concepts

- Attach file downloads to membership levels so PMPro can sell plugins.
- When Memberships are sold or awarded, provide file downloads and issue license keys for the delivery of updates.
- One license key per use regardless of how many plugins have been ordered. One key validates all purchases.
- Files are stored at `wp-content/uploads/pmpro-plugin-licensing/`
- Seamless updates in customer WordPress dashboards with core UI.

### TODO License activation & update downloads

- [ ] Rewrite the updater based on the sample plugin updater, make it just usable by sample plugin

### TODO File management

- [ ] Replace button, Remove button, and Version aren't immediately shown after an upload

### TODO misc housekeeping

- [ ] Buy PMPro. Buy that membership with a customer account and find the .zip downloads and license key
- [ ] Clone the settings page banner CSS so a PMPro redesign does not break my tab, this looks good https://www.linode.com/support/contact/
- [ ] Fake a purchase
- [X] Put .zip files uploaded through our modal in a specific /uploads subfolder

## TODO Documentation in this README plus a web page

- [ ] Protect downloads in our uploads folder from non-members
- [ ] Verify that your uploads folder is not served by a CDN (e.g. with WP Engine and some other hosts).
- [ ] Make sure the Level Group these levels are a part of allows multiple selections. This is the key to allowing people to purchase more than one downloadable within the group, without losing access to their previous purchase.
- [ ] Does anything stop working besides updates with an invalid license? No, that would mean more checks.

## Developer notes & tools

### License statuses

1. `invalid` not found in any user meta or the file cannot be found
2. `expired` found/valid but customer no longer paying the bill
3. `inactive` found/valid but this site cannot be activated or the license does not provide access to this file
4. `active` found/valid and this site is in the active sites list

### How to force an update check

The first two are equivalent. WordPress stores plugin update info in a site transient, which is an option with a prefix:

1. `wp transient delete update_plugins --network `
2. `wp option delete _site_transient_update_plugins `

__Erase the data stored by this plugin to force a reconnection to the store__

`wp option delete pmpro_pl_sample_plugin_update_response`

### Database queries

#### List all file hashes

```
SELECT p.ID as attachment_id, p.post_title as title, p.post_name as slug, p.guid as file_url, pm.meta_value as file_hash, p.post_date as created_date, p.post_modified as modified_date
FROM wp_posts p INNER JOIN wp_postmeta pm ON p.ID = pm.post_id
WHERE p.post_type = 'attachment' AND p.post_status = 'inherit' AND pm.meta_key = 'pmpro_pl_hash' AND pm.meta_value != ''
ORDER BY p.post_title ASC; 
```

#### List all license keys

```
SELECT id, user_login, user_email, meta_value
FROM `wp_users` LEFT JOIN `wp_usermeta` on id = user_id
WHERE meta_key = 'pmpro_plugin_licensing_license_key'
ORDER BY id ASC; 
```
