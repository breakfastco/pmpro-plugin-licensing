# pmpro-plugin-licensing

WordPress plugin. Add-on for Paid Memberships Pro. Sells WordPress plugins &amp; provide updates.

## Concepts

- One license key per user (like Gravity Forms & Gravity Wiz), regardless of how many plugins have been orders, one key validates all purchases.
- Attach file downloads to membership levels so PMPro can sell plugins.
- Issue license keys for the delivery of updates to active subscribers.

## TODO

- [X] Manually give myself a membership, no license key generated in user meta.
- [X] Add an updater to pmpro-pl-sample-plugin from akamaiwp.breakfastco.xyz store
- [X] Document how to force the update check and debug the respose from akamaiwp.breakfastco.xyz
  `wp option delete _site_transient_update_plugins`
  `wp option delete pmpro_pl_sample_plugin_update_response`
- [X] Display extracted version number in the files table
- [X] Put .org readme.txt to HTML logic in the extraction during upload
- [ ] replace button isn't immediately added when choose zip file
- [ ] List, add & remove active sites when I click "5 sites"
- [ ] Buy PMPro. Buy that membership with a customer account and find the .zip downloads and license key
- [ ] Clone the settings page banner CSS so a PMPro redesign does not break my tab, this looks good https://www.linode.com/support/contact/
- [ ] Build active/valid license check into settings page and use a green check mark like Gravity Wiz
  - [ ] Fake a purchase
- [X] Put .zip files uploaded through our modal in a specific /uploads subfolder
- [ ] Protect downloads in our uploads folder from non-members

## Setup concerns

- [ ] Verify that your uploads folder is not served by a CDN (e.g. with WP Engine and some other hosts).
- [ ] Make sure the Level Group these levels are a part of allows multiple selections. This is the key to allowing people to purchase more than one downloadable within the group, without losing access to their previous purchase.

## Tools

### List all file hashes

```
SELECT p.ID as attachment_id, p.post_title as title, p.post_name as slug, p.guid as file_url, pm.meta_value as file_hash, p.post_date as created_date, p.post_modified as modified_date FROM wpbc_posts p INNER JOIN wpbc_postmeta pm ON p.ID = pm.post_id WHERE p.post_type = 'attachment' AND p.post_status = 'inherit' AND pm.meta_key = 'pmpro_pl_hash' AND pm.meta_value != '' ORDER BY p.post_title ASC; 
```

### List all license keys
