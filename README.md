# pmpro-plugin-licensing

WordPress plugin. Add-on for Paid Memberships Pro. Sells WordPress plugins &amp; provide updates.

## Concepts

- One license key per user (like Gravity Forms & Gravity Wiz), regardless of how many plugins have been orders, one key validates all purchases.
- Attach file downloads to membership levels so PMPro can sell plugins.
- Issue license keys for the delivery of updates to active subscribers.

## TODO

- [ ] Stop faking the update/license check. Design bruno request called by check_updates()
- [ ] Buy PMPro. Buy that membership with a customer account and find the .zip downloads and license key
- [ ] When the .zip is uploaded to the store, extract the readme.txt and stash the values in meta fields on the attachment
- [ ] Clone the settings page banner CSS so a PMPro redesign does not break my tab, this looks good https://www.linode.com/support/contact/
- [ ] Build active/valid license check into settings page and use a green check mark like Gravity Wiz
  - [ ] Fake a purchase
- [X] Put .zip files uploaded through our modal in a specific /uploads subfolder
- [ ] Protect downloads in our uploads folder from non-members

## Setup concerns

- [ ] Verify that your uploads folder is not served by a CDN (e.g. with WP Engine and some other hosts).
- [ ] Make sure the Level Group these levels are a part of allows multiple selections. This is the key to allowing people to purchase more than one downloadable within the group, without losing access to their previous purchase.
