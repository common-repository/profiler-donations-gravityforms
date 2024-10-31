![Profiler + Gravity Forms](https://mediarealm.com.au/wp-content/uploads/2021/12/Profiler-GravityForms-Banner-1200x450.png)

# [Profiler Donations / Gravity Forms - Wordpress Plugin](http://mediarealm.com.au/wordpress-plugins/profiler-gravity-forms-donation-plugin/)
A Wordpress plugin to integrate your Gravity Forms with Profiler CRM. [Visit the plugin's website](http://mediarealm.com.au/wordpress-plugins/profiler-gravity-forms-donation-plugin/), and the [official WordPress.org entry](https://wordpress.org/plugins/profiler-donations-gravityforms/).

This plugin is free software, distributd under the GPLv2 license. See the full terms of the license in the file `LICENSE.md` and the disclaimer at the bottom of this `README.md` file.

## Setup

This guide will help you configure your Profiler system for RAPID integration with the Wordpress Gravity Forms plugin.

### Prerequisites

*	[WordPress](https://wordpress.org) site with Administrative access
*	[Gravity Forms](http://www.gravityforms.com)
*	Payment Gateway integration with Gravity Forms (must be pre-existing and use standard Gravity Forms payment hooks and database fields)
  * For Australian non-profits, we recommend the official Gravity Forms Stripe plugin
*	Full administrative access to Profiler
*	CURL and XML Enabled on Web Server

### 1. Installing the Gravity Forms plugin

1.	Login to your Wordpress site
2.	Navigate to the Plugins page
3.	Click “Add New” (in the header)
5.	Search for 'Profiler Integration for Gravity Forms' and click 'Install'
6.	Click “Activate Plugin”

### 2. Configure your Donation Form

This webpage has the official setup instructions: https://support.profiler.net.au/kb/linking-a-payment-donation-gravity-form-to-profiler-using-the-plugin/

### 3. Test the form

Testing the form is very important. Make sure you test all combinations, pre-defined amounts and other options. Ensure all data is correctly passed through to Profiler. **It's your responsibility to test all functionality to ensure it performs to your requriements.**

## How to Override the Source and Acquisition Codes

There are two ways to override the default source and acquisition codes:

1.	GET Parameters
2.	Short Code

Here is an example source code you can use:

   [donate_setoptions sourcecode=”WEBDON” pledgesourcecode=”REGULAR” pledgeacquisitioncode=”WEBPLEDGE”]

Ensure you embed this shortcode before the Gravity Form shortcode on the donation page. You can use this to create campaign-specific donation pages, with only one Gravity Form powering it all.

Here is an example query string you can use:
http://example.com/donate/?sourcecode=WEBDON&pledgesourcecode=REGULAR&pledgeacquisitioncode=WEBPLEDGE

If both a GET Parameter and Short Code are used on the same page, the GET parameter will take precedence.

## RAPID Quick Guide

When donations are sent to Profiler, they appear on the *Donations > Integration > RAPID Integration* screen.

To see all donations regardless of status, click on the "List Filter" drop-down and select "All/Force". The latest donations will show at the top of the list.

If a Donation says "Continue":

1.	Click the "Continue" button
2.	Manually match the client (or create a new one)
3.	Press the "Finish/Back" button

If a Donation says "Check":

1.	Click the "Check" button
2.	Check the "Entered in RAPID" and "Entered in Profiler" data, and make sure the correct new details are stored in the far-right column.
3.	Press "Save" (or create a new client)

If a Donation was entered by mistake or cannot be processed, you need to press the "Remove" link

## Disclaimer

THERE IS NO WARRANTY FOR THIS PROGRAM. IT IS PROVIDED "AS IS" WITHOUT WARRANTY OF ANY KIND, EITHER EXPRESSED OR IMPLIED, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE. THE ENTIRE RISK AS TO THE QUALITY AND PERFORMANCE OF THE PROGRAM IS WITH YOU. SHOULD THE PROGRAM PROVE DEFECTIVE, YOU ASSUME THE COST OF ALL NECESSARY SERVICING, REPAIR OR CORRECTION. 

IN NO EVENT UNLESS REQUIRED BY APPLICABLE LAW OR AGREED TO IN WRITING WILL ANY COPYRIGHT HOLDER, OR ANY OTHER PARTY WHO MAY MODIFY AND/OR REDISTRIBUTE THE PROGRAM AS PERMITTED ABOVE, BE LIABLE TO YOU FOR DAMAGES, INCLUDING ANY GENERAL, SPECIAL, INCIDENTAL OR CONSEQUENTIAL DAMAGES ARISING OUT OF THE USE OR INABILITY TO USE THE PROGRAM (INCLUDING BUT NOT LIMITED TO LOSS OF DATA OR DATA BEING RENDERED INACCURATE OR LOSSES SUSTAINED BY YOU OR THIRD PARTIES OR A FAILURE OF THE PROGRAM TO OPERATE WITH ANY OTHER PROGRAMS), EVEN IF SUCH HOLDER OR OTHER PARTY HAS BEEN ADVISED OF THE POSSIBILITY OF SUCH DAMAGES.
