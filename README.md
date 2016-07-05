# mysqldump-diff
This PHP class takes two data only MySQL backups (dumps with no table structure changes) and puts the differences between them in a third file, generating a data update file. Script takes into account mysqldump formats from phpMyAdmin3.4 or Backup &amp; Migrate module for Drupal 7.

Dumps must contain exported tables structures (CREATE TABLE IF NOT EXISTS) in order to convert old INSERTS into DELETES or new INSERTS into UPDATES.

BE AWARE THAT BIG DATA DUMPS CONSUME A LOT OF MEMORY TO BE PROCESSED! I recommend no more than 5.000 to 10.000 individual INSERTS per file.