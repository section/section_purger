# drupal-purger
This module allows Section’s global, distributed caching layer to quickly respond to invalidation events from a Drupal instance in exactly the same way that Drupal’s internal cache or a local varnish cache running on the host machine does, ensuring that the content in Section’s global caching layer is always up to date.

## Installation

For installation instructions please [go here](https://www.section.io/docs/how-to/drupal-setup/drupal8/)

## Development

Notes on Architecture:

- Assuming you have the purge module installed, navigate to the Purger's UI at `/admin/config/development/performance/purge`
- This module depends on the key module to store your password for aperture. Make sure your password is correct because the purger will send hundreds of requests to the API which could potentially lock out your account if the authentication attempts fail.
- Drupal will queue bans with the Core tags queuer, but make sure you have a processor installed (cron, or LateRuntimeProcessor)
- There are many other invalidation types that you can use with other processors and queuers. See the table below
- The actual purger functions are performed by code inside `src/Plugin/Purge/Purger/SectionPurger*`.
- The configuration form is controlled by code in `src/Form`. Data input via this form is stored in `/src/Entity/SectionPurgerSettings.php`. If you want to create a hardcoded variable value that is not customer facing, simply include this variable in this file and make no reference to it in the form. If you'd like a default value that is subsequently adjustable by the user, add that variable with the default value to `SectionPurgerSettings.php` and add its field to the user input form. There are examples of both of these in the existing codebase. Note that in order for a variable to be overwritten by the form input, it needs to be enumerated in the `section_purger.schema.yml`
- This module was developed as a fork of the generic http purger. Any questions on the development history can be answered by comparing the current state to that project — the git history is rather mangled due to moving between repos.
- The generic HTTP purger also contained code for a Bundled Purger (which lived in the Purger directory alongside the Section Purgers). The functionality of this has not been comprehensively understood, but in the abstract it coalesces multiple ban requests into a single API call. Because the choke point for this operation was believed to be Varnish Cache's ability to process bans rather than the API's ability to accept requests, this mode was determined to be unnecessary at the time. If this functionality is desired, the relevant code (it was not adapted for Section in any way before deletion) can be recovered from version control or from the source HTTP Purger.
- The plugin currently supports an optional sitename feature designed to support Drupal multisites. If no sitename is specified, then the plugin clears cache for any pages with the relevant cache tags. If the sitename is filled in, the plugin appends a check for the specified hostname. Each Drupal site within a multisite has its own admin dashboard and its own UI in which to enable the purger.
- If you wish to uninstall the Plugin from a live site for the purposes of testing, the most efficient way to do so is to uninstall the plugin from the admin console. Once it has been uninstalled there, you can delete it from the filesystem of the live box (Drupal may overwrite old files of the same name if you try and upload the same module again — this has not been proven). Deleting the module from the filesystem while it is still installed will cause a total failure of the site. The only known fix for this is a reinstall of Drupal as a whole, although there are likely easier ways to fix it. 


| Invalidation Type | Description                                                                                                                                            |
|-------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------|
| domain            | Invalidates an entire domain name. e.g.: example.com                                                                                                   |
| everything        | Invalidates Everything. No argument required.                                                                                                          |
| tag               | Invalidates by Drupal cache tag, e.g.: menu:footer                                                                                                     |
| url               | Invalidates by URL, e.g. http://site.com/node/1  (***NOTE:** this, by nature, does not use the sitename configured in the purger, but rather whatever url fed to this type will be purged from varnish. Also, the protocol is specific; for example if invalidating an http request, the https equivalent will not be invalidated*)                |
| wildcardurl       | Invalidates by URL, e.g. http://site.com/node/*  (see note above)                                                                                                      |
| path              | Invalidates by path, e.g. news/article-1. This should not start with a slash, and should not contain the hostname.                                     |
| wildcardpath      | Invalidates by path, e.g. news/*                                                                                                                       |
| regex             | This doesn't actually invalidate by regular expression. We used this pre-defined type to implement logic for purging by varnish ban expressions.e.g. obj.status == 404 && req.url ~ node\/(?).*. |

Read more about the default invalidation types in [the purge module docs](https://www.drupal.org/project/purge/releases/8.x-3.0-beta1#invalidation-types)