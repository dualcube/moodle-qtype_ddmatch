# Drag-and-Drop Matching Question

A Moodle question type that lets students match items by **dragging and
dropping** them, instead of picking answers from dropdown menus. It's built
as an extension of the core Matching question type, so question authoring
and grading behave exactly the way teachers already expect.

> This plugin is maintained by [DualCube](https://dualcube.com).

## Overview

The drag-and-drop question is adapted from the existing Matching question.
The teacher editing interface and the grading logic are unchanged — the
non-JavaScript fallback looks identical to the original Matching question.

In the drag-and-drop student interface, all the answers are listed to the
right of the question table. Students:

- Drag an answer from the list onto any question target. The target is
  highlighted to show exactly where the answer will land.
- Reuse an answer multiple times, if needed, by dragging it to another
  target.
- Change an answer by dragging it off its target, or by dropping a
  different answer on top of it.

## Requirements

* Moodle 5.0 – 5.3

## Installation

1. Go to **Site administration > Plugins > Install plugins**, and either
   upload the plugin ZIP or drag and drop it onto the page.

   Alternatively, install it manually:
   1. Place the plugin's files in `question/type/ddmatch`.
   2. Visit `admin/index.php` in your browser to complete the installation.

## Usage

From within a course, go to **Course > Add an activity or resource > Quiz > Question bank > Create a new question**, then choose **Drag-and-Drop Matching** from the list of question types.

## Grading

Grading is identical to the standard Matching question type.

## Uninstallation

Go to **Site administration > Plugins > Plugins overview**, find **Drag-and-Drop Matching Question**, and select **Uninstall**.
