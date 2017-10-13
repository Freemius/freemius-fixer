# Why?
The [Freemius WordPress SDK](https://github.com/freemius/wordpress-sdk) has quite a complex data and caching layer. 
Some edge cases may lead to a corruption of the data integrity, especially when the SDK is running on a site that has a clone. 
While we are doing our best to resolve all those cases, it's not feasible to test the SDK in all possible WordPress environments.

# What?
This plugin was specially made for edge case like that. It does two simple things:
1. The ability to dump the Freemius data into a file so we can later troubleshoot and investigate the edge-case.
2. Cleanup of the Freemius data to start fresh.

![Plugin settings](/screenshot-1.png)

# When?
When a user of your plugin/theme encounters into an edge-case with the SDK, most likely, they will receive some fatal PHP error
that:
1. Either prevents them from activating the product.
2. Or, breaking their site's rendering.

When it's #2, the immediate thing that needs to be done is renaming the plugin/theme folder name to force the deactivation of the plugin/theme and recovering the site rendering. 
The easiest way to do it is via FTP. If the user is tech-savvy, they can do it themselves. If they don't, ask for their FTP credentials and simply add `_` as a suffix to the plugin/theme folder name.
For example, if the plugin's folder name is `awesome-plugin`, rename it to `awesome-plugin_`.

After ensuring that the problematic plugin/theme is deactivated, ask the user:
1. Send you the description of the error (a screenshot is even better). 
2. Install and activate this fixer plugin.
3. Download the Freemius data (without cleaning the fix button). 
4. Send you a screenshot of the _Freemius Debug_ page `/wp-admin/admin.php?page=freemius`

If the _Freemius Debug_ page doesn't exist, that means that there are no other active plugins and themes that are running Freemius. 
This is a safe case to tell the user they can click the "Fix" option to cleanup the Freemius data. 
After the data was cleaned up, the user, or you, will need to rename the deactivated plugin/theme's folder name back by removing the `_` suffix.
And the extension is now ready for reactivation.

If the _Freemius Debug_ page does exist, it means that there are other active Freemius powered extensions on the site. 
In this case, please do not proceed with the "Fix" and contact support@freemius.com for further assistance.

In both cases, please send us all the information to support@freemius.com so we can investigate the issue.

# IMPORTANT
The plugin should be used only as the last resort since it cleans all Freemius data, including the data that was stored for other Freemius powered plugins and themes that were installed and potentially are running on the site.
