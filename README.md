# pmpro-plugin-licensing

WordPress plugin. Add-on for Paid Memberships Pro. Sells WordPress plugins &amp; provide updates.

## Concepts

- One license key per user (like Gravity Forms & Gravity Wiz), regardless of how many plugins have been orders, one key validates all purchases.
- Attach file downloads to membership levels so PMPro can sell plugins.
- Issue license keys for the delivery of updates to active subscribers.

## How to force the update check

The first two are equivalent, it's a site option:

1. `wp transient delete update_plugins --network `
2. `wp option delete _site_transient_update_plugins `
3. `wp option delete pmpro_pl_sample_plugin_update_response` This wipes out the API response for the sample plugin.

### TODO License activation & update downloads

- [X] Enable Updates plugin action link goes to license key management page
- [ ] Build active/valid license check into settings page and use a green check mark like Gravity Wiz
- [ ] Enable Updates should also show for expired license or inactive site
- [ ] Does anything stop working besides updates with an invalid license?
- [ ] Rewrite the updater based on the sample plugin updater, make it just usable by sample plugin

### TODO Active sites

- [ ] Make sure the list works by adding the sample plugin to another site
- [ ] License checks add to the list unless the list is full, then they need a match
- [ ] Admin can add sites beyond the limit

### TODO File management

- [ ] Replace button isn't immediately added when choose zip file
- [ ] Protect downloads in our uploads folder from non-members

### TODO misc housekeeping

- [ ] Buy PMPro. Buy that membership with a customer account and find the .zip downloads and license key
- [ ] Clone the settings page banner CSS so a PMPro redesign does not break my tab, this looks good https://www.linode.com/support/contact/
- [ ] Fake a purchase
- [X] Put .zip files uploaded through our modal in a specific /uploads subfolder

## Setup concerns

- [ ] Verify that your uploads folder is not served by a CDN (e.g. with WP Engine and some other hosts).
- [ ] Make sure the Level Group these levels are a part of allows multiple selections. This is the key to allowing people to purchase more than one downloadable within the group, without losing access to their previous purchase.

## Tools

### List all file hashes

```
SELECT p.ID as attachment_id, p.post_title as title, p.post_name as slug, p.guid as file_url, pm.meta_value as file_hash, p.post_date as created_date, p.post_modified as modified_date FROM wpbc_posts p INNER JOIN wpbc_postmeta pm ON p.ID = pm.post_id WHERE p.post_type = 'attachment' AND p.post_status = 'inherit' AND pm.meta_key = 'pmpro_pl_hash' AND pm.meta_value != '' ORDER BY p.post_title ASC; 
```

### List all license keys
