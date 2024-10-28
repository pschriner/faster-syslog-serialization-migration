# Faster syslog serialization migration

The syslog serialization migration update wizard is painfully slow if you have a large history.

This extension allows to things:

* it adds a field to allow a migration before the actual update
* it adds and index to make the queries used faster in case it has to run more than once

## Usage

1) install the extension
   > Note: adding the column and index can take a lot of time
2) run the command as often as needed
3) once on v12, run the upgrade wizard which is a replacement of the original upgrade wizard
4) uninstall the extension
   > Note: use the database compare or other means to remove the surplus field and index. This will take some time
