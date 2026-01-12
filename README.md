# Scored Articles Plugin for TT-RSS

This plugin creates a new virtual feed called "Scored articles" that appears in the left sidebar under the "Special"
category, alongside built-in feeds like "Starred" and "Published".

It displays all articles that have a score of 1 or higher, ordered by score (highest first) and then by date.

## Installation

1. Clone this repository as `vf_scored` under the `plugins.local` directory of your TT-RSS installation:
    ```bash
    cd /path/to/tt-rss/plugins.local
    git clone https://github.com/andreoliwa/tt-rss-plugin-vf-scored vf_scored
    ```
2. Enable the plugin in the User Interface:
    - This is a **user plugin**, not a system plugin
    - Go to Preferences → Plugins
    - Enable "vf_scored" in the plugin list
    - The "Scored articles" feed will appear in your left sidebar under "Special"
