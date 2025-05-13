# pmpro-plugin-licensing

WordPress plugin. Add-on for Paid Memberships Pro. Sells WordPress plugins &amp; provide updates.

## Concepts

- One license key per user (like Gravity Forms & Gravity Wiz), regardless of how many plugins have been orders, one key validates all purchases.
- Attach file downloads to membership levels so PMPro can sell plugins.
- Issue license keys for the delivery of updates to active subscribers.

## TODO

- [X] Get the core functionality working, slap file uploaders on membership levels
- [X] Generate and store license keys with orders
- [X] When the .zip is uploaded to the store...

  - [X] extract the version number from the plugin .zip and put it in the table
  - [X] extract the readme.txt and stash the values in meta fields on the attachment
  - [X] generate a hash that will represent the files in download URLs like `https://breakfastco.xyz/membership-account/download/9234f4y9hf43fhgq34fh39f4h4` so we don't leak file names

#### package/download links for updater

- [ ] REST endpoint for "next version details" from store/Make updates live with download links that respond from WP requests including license key

#### Activation & validation of license, active sites

- [ ] Maintain a list of domain/host names where the file download has been activated

## Lower priority TODO but still needed

- [ ] Clone the settings page banner CSS so a PMPro redesign does not break my tab, this looks good `https://www.linode.com/support/contact/`
- [ ] Build active/valid license check into settings page and use a green check mark like Gravity Wiz
- [X] Put .zip files uploaded through our modal in a specific /uploads subfolder
- [ ] Protect downloads in our uploads folder from non-members
- [ ] (My store only?) Go through the cart and change all the text to look like a shopping cart
- [X] flush rewrite rules on activate and deactivate for download links to work
- [ ] Make sure the hashes for files are unique among all file hashes the same way we do license keys
- [ ] Run Plugin Check/look for untranslateable strings.
- [ ] Move CSS and JS out of settings class and into their own files

## Setup concerns

- [ ] Verify that your uploads folder is not served by a CDN (e.g. with WP Engine and some other hosts).
- [ ] Make sure the Level Group these levels are a part of allows multiple selections. This is the key to allowing people to purchase more than one downloadable within the group, without losing access to their previous purchase.
- [ ] Plugins sold must have readme.txt in root folder with version number in header. Description, Installation, FAQ, and Changelog sections will fill out the "version 2.2.2 details" pop-up
