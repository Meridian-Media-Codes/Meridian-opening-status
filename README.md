# Meridian Opening Status

A lightweight WordPress plugin for showing a live open/closed message in a header top bar.

## Installation

1. In WordPress, go to Plugins → Add New Plugin → Upload Plugin.
2. Upload `meridian-opening-status.zip`.
3. Activate the plugin.
4. Go to Settings → Opening Status.
5. Set the phone number, contact URL, timezone and weekly hours.
6. Add `[opening_status]` to a Shortcode block in the header.

## Recommended Kadence header use

For a dedicated full-width strip:

`[opening_status]`

To place it inside an existing styled top row, like the phone number in the supplied header:

`[opening_status bare="yes" full_width="no"]`

## Features

- Weekly opening hours
- Closed days
- Overnight opening hours, such as 17:00 to 01:00
- Click-to-call message while open
- Contact-page link while closed
- Automatic, force-open and force-closed modes
- Configurable colours, alignment, font size and spacing
- Shortcode and classic WordPress widget
- Live REST refresh to stay accurate through page caching
- Custom CSS class support

## Shortcode options

- `bare="yes"` removes the plugin background and padding
- `full_width="no"` allows inline placement
- `class="your-class"` adds custom classes

Example:

`[opening_status bare="yes" full_width="no" class="ahl-opening-status"]`
