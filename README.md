# Plugin: Company Points & Triggers by Leuchtfeuer

## Overview

Massively enhanced Company-based Scoring. Point-based and even other (!) triggers and multiple triggered actions, all that for Companies.

Company Points & Triggers is part of the "ABM" suite of plugins that extends Mautic capabilities for working with Companies.

## Requirements
- Mautic 6
- Company Tags and Company Segments Plugins

## Installation
### Composer
This plugin can be installed through composer.

### Manual install
Alternatively, it can be installed manually, following the usual steps:

* Download the plugin
* Unzip to the Mautic `plugins` directory
* Rename folder to `LeuchtfeuerCompanyPointsBundle` 

-
* In the Mautic backend, go to the `Plugins` page as an administrator
* Click on the `Install/Upgrade Plugins` button to install the Plugin.

OR

* If you have shell access, execute `php bin\console cache:clear` and `php bin\console mautic:plugins:reload` to install the plugins.

## Plugin Activation and Configuration
1. Go to `Plugins` page
2. Click on the `Company Points` plugin
3. ENABLE the plugin

## Usage
### Overview
The plugin brings a new menu item `Companies -> Company Points & Triggers`.
Here you can define point-based but also behavior-based triggered actions.

### Point types and calculation
The traditional (static) "Company Points" are unchanged.
There are currently no automated "Point Actions" (i.e. points being automatically added to the Company when a certain condition is met) but of course the traditional campaign actions for this.

On top of that, this plugin adds a "Score calculated", aggregated (across company members) by a console command:
`php bin/console leuchtfeuer:abm:points-update`
You should set up a cron entry accordingly.

The only current algorithm for the aggregation is "static company points PLUS average among all contacts that currently have points".
* This also includes contacts who have this company as secondary.
* This does not include contacts who have zero points.
  
Changes of "Score calculated" are reflected in the audit log and company timeline.

### Triggers and Triggered Actions
Under "Company Points & Triggers", you can define conditions ("Triggers") and assign actions to take ("Triggered Actions").
(Note that for traditional contact Points, the wording is different: instead of "Triggered Actions", the term "events" is being used.)

In the trigger, you can define
* type of trigger (points or member contact behaviour)
* details per trigger type
* optional: Limitation to Company Segment

The Trigger type "Points" allows to set the number of Points that it takes to invoke the trigger. This refers to "Points Calculated".

The Trigger type "Company member activity" reacts to contact activity which matches the desired criteria (e.g. "First activity of every new contact"). Activity, in this context, is everything that changes the "last active" timestamp of a contact (e.g. page visit, email link click).

Current choices of triggered actions:
* Modify Company tags
* Modify Contact campaigns (allows to choose WHICH contact to invoke, e.g. youngest / oldest / all / all known contacts or even the placeholder contact)
* Send email to user

An audit log entry is created for each Company Point Trigger created, updated or deleted.



## Troubleshooting
Make sure you have not only installed but also enabled the Plugin.

If things are still funny, please try

`php bin/console cache:clear`

and 

`php bin/console mautic:assets:generate`

## Known Issues
* In contact-related Triggered Actions ("change campaign"), options like "oldest" should exclude the "placeholder contact"

## Future Ideas
* Choice of aggregation algorithms (including time)
* Additional Triggered Actions like `Modify Company Segments`
* Support for Point Groups
* Adding Company Points as a Triggered Action (would only make sense for non-point based Trigger types)

## Credits
* @biozshock
* @ekkeguembel
* @JonasLudwig1998
* @lenonleite
* @LeonOltmanns
* @MadlenF
* @PatrickJenkner
* @patrykgruszka

## Author and Contact
Leuchtfeuer Digital Marketing GmbH

Please raise any issues in GitHub.

For all other things, please email mautic-plugins@Leuchtfeuer.com
