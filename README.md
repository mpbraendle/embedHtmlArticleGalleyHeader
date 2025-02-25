# embedHtmlArticleGalleyHeader
OJS embedded HTML Article Galley including header and footer 

## Description
This OJS plugin provides rendering support for HTML Article Galleys and supports iPad's. Instead of iframes, the plugin fetches the content of a HTML file and embeds it to the page.

The plugin also displays the journal's header and footer for consistent branding across a journal's website. Also, it uses the standard OJS breadcrumb instead of the one used in the HtmlArticleGalley plugin.

Compatible with OJS 3.3.0-x (stable 3.3.0 branch)

Compatible with OJS 3.4.0-x (master branch)

Based on work https://github.com/ajnyga/embedHtmlArticleGalley by @ajnyga and the The Federation of Finnish Learned Societies (https://tsv.fi/en/).

## TODO
In templates/display.tpl, there are some local additions (display of license, a JavaScript for a specific journal) that should be moved out and be made configurable.

Therefore, the plugin requires a configuration page, where the following properties can be configured:

- display of journal header/footer 
- display of the license with the article
- script to be included after the galley HTML
