# SiteDownloader

A StaticForge feature package that downloads a remote site and converts the content to markdown.

This is no where near a perfect conversion. However, it is good enough to get your content. You will need to do some cleanup and reorganization after the download.

I've been using this to convert some old WordPress sites to StaticForge static sites.

## Installation

```bash
composer require calevans/staticforge-sitedownloader
php vendor/bin/staticforge feature:setup sitedownloader
```

1. Create a new directory for your site.
2. Install staticforge
3. run `php vendor/bin/staticforge site:init`
3. Install this package
4. Configure .env and siteconfigure.yaml as needed.
5. Run `php vendor/bin/staticforge site:download --url=YOUR_DOMAIN_HERE` to download and convert the remote site.
6. Create a template or use one of the templates we provide.
7. Render your site and then use site:devserver to view it. Repeate this as often as needed.
8. Once you are happy with it, fill out the `# SFTP Upload Configuration` section of `.env` file.
9. Run `php vendor/bin/staticforge site:upload` to upload your site to your server.
