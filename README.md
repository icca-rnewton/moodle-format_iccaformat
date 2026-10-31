# ICCA Format (format_iccaformat)

A card-based course format for Moodle 4.5, built by the Inns of Court College of Advocacy (ICCA).

Sections, subsections and activities are shown as visual cards with a type label, completion
progress, and clear badges for hidden or restricted content. A course map at the top of each
section lets learners move between sections and subsections.

## Features

- **Card layouts** for the course home page and section pages, with configurable card size,
  corner style, hover colour and completion display.
- **Course map**: a strip of section cards with the selected section's subsections below,
  with optional row headings that use your Card Styling Rules labels.
- **Card Styling Rules**: set the label, icon and default image for each activity type,
  site-wide or per course, with bulk editing.
- **Card Image Library**: a shared library of card images, site-wide and per course.
- **Per-card overrides**: editors can change an individual card's image, label and icon,
  with a searchable icon picker.
- **Staff visibility**: hidden cards are dimmed and marked "Hidden from students";
  restricted cards show their conditions in a dropdown.
- **Out of the box**: a fresh install adds a bundled set of 26 card images and the default
  Card Styling Rules, already linked.

## Requirements

- Moodle 4.5 or later.

## Installation

1. Copy this folder to `course/format/iccaformat` in your Moodle installation.
2. Visit *Site administration > Notifications* to complete the install.
3. Choose **ICCA Format** in a course's settings under *Course format*.

On first install, the bundled images in `pix/cardimages/` are copied into the site-wide Card
Image Library and linked to the default Card Styling Rules. After that, the library copies are
what cards use; admins can rename, replace or remove them like any other library image.

## Bundled images

`pix/cardimages/` holds one SVG per activity type, named after the module (for example
`quiz.svg`, `forum.svg`), plus `section.svg` and `subsection.svg`. To change what fresh installs
receive, replace these files using the same lowercase names. Existing sites are not affected.

## Privacy

The only personal data stored is the uploader of each library image. When a user's data is
erased, that attribution is removed and the image itself is kept, as it is shared course content.

## Licence

GNU GPL v3 or later. Copyright 2026 Inns of Court College of Advocacy (Part of COIC).
