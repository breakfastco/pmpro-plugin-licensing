# pmpro-plugin-licensing

WordPress plugin. Add-on for Paid Memberships Pro. Sells WordPress plugins &amp; provide updates.

## Concepts

- One license key per user (like Gravity Forms & Gravity Wiz), regardless of how many plugins have been orders, one key validates all purchases.
- Attach file downloads to membership levels so PMPro can sell plugins.
- Issue license keys for the delivery of updates to active subscribers.

## TODO

- [X] Get the core functionality working, slap file uploaders on membership levels
- [ ] Generate and store license keys with orders
- [ ] Buy that membership with a customer account and find the .zip downloads and license key
- [ ] Make the next version readme/update data available via REST to fill out the "View version 1.2.3 details" link popup
- [ ] Clone the settings page banner CSS so a PMPro redesign does not break my tab, this looks good https://www.linode.com/support/contact/
- [ ] Build active/valid license check into settings page and use a green check mark like Gravity Wiz
- [ ] Put .zip files uploaded through our modal in a specific /uploads subfolder

## Setup concerns

- [ ] Verify that your uploads folder is not served by a CDN (e.g. with WP Engine and some other hosts).
- [ ] Make sure the Level Group these levels are a part of allows multiple selections. This is the key to allowing people to purchase more than one downloadable within the group, without losing access to their previous purchase.
