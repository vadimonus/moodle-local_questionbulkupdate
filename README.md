Questions bulk update Moodle plugin
===================================

Requirements
------------
- Moodle 4.0 (build 2022041900) or later.

Installation
------------
Copy the bulkupdate folder into your Moodle /question/bank directory and visit your Admin Notification page to complete the installation.

Usage
-----
This plugin is intended to update questions after import from formats, that do not allow to specify numbering or
default grade. Question bank navigation node will be extended with "Questions bulk update" item. Select category with
desired questions and fill options, that you want to update.

Author
------
- Vadim Dvorovenko (Vadimon@mail.ru)

Links
-----
- Updates: https://moodle.org/plugins/view.php?plugin=qbank_bulkupdate
- Latest code: https://github.com/vadimonus/moodle-qbank_bulkupdate

Changes
-------
- Release 0.9 (build 2021010500):
    - Initial release.
- Release 1.0 (build 2021092400):
    - Fix errors in 3.1 - 3.6.
- Release 2.0 (build 2025021500)
    - Renamed from local_questionbulkupdate to qbank_bulkupdate.
    - Refactored for Moodle 4 question bank changes.
