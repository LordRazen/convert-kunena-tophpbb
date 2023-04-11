# MIGRATION FROM JOOMLA 3.10 / KUNENA 5.0.X to PHPBB 3.3

This is a migration from Joomla 3 with Kunena 5 to a fresh phpbb 3.3 installation. 

The migration is not made as a "out of the box" solution and it requires some knowledge about php, composer, databases etc. 

However, since no other converter worked for me I started my own untill I was happy with the result. 

Some parts might also not work out for you, so I commented a lot and you might see some handy var_dumps to uncomment if you need.

And nope I had no interest in putting even more effort into this since I'm happy to get the job done ;-)

Feel free to do a PR if you want.

# Installation
- Clone the git repository
- Install it with "composer install"
- Also make a blank local installation of phpbb and add all your custom bbcodes to it.
- Run the index.php a first time in order to generate the work folder and the config file
- Adjust the config so the migration can connect to your old joomla database and the new phpbb database

# Preparations
Since I didnt find a good solution to calculate phpbbs left_id / right_id system I created the forum structure 
by hand and ensured the ids of the forums are correct. And yep, that means creating a random forum and deleting 
it again if you have a gab in the ids of your forum. Else the phpbb left_id / right_id calculation will be messed
up and I didnt find a good tool to fix it. Save this database after you did this! You don't wanna create all forums
twice!

In case someone wanna add a good Tec for this - here's the explanation: 
https://www.sitepoint.com/hierarchical-data-database-2/

Also make a backup of the following phpbb tables:
- phpbb_attachments
- phpbb_forums
- phpbb_posts
- phpbb_topics
- phpbb_topics_posted
- phpbb_users

# Config
- The DB Settings should be clear ^^
- joomla_url: URL as like https://google.com/, the last slash is important.
- match_user_kunenaId_phpbbId: If you wanna match a kunenaID to a phpBB id, do it like this: '{"775":"2"}'
- job: The job which should be run right now. If the migration is done, this is empty. It starts at user, then topic, then overview.
- migrations_at_once: How many user / topic migrations are done with one run
- last_xxx: ID of the last x which was migrated
- forum_depth: In order to fix the ForumOverview, the whole calculation is iterated a few times. Set this to the max depth of your forum from the highest forum to the lowest nested forum. Should probably be 3 or 4.

# Run the migration
Run the index.php as a cronjob untill everything is migrated. What's done: 
- Users are migrated including the avatars
- Topics, Posts and Attachments are migrated
- The Threads and Posts shown in the Forum Overview are adjusted since this is not done automatically but stored in the forum table.

BTW: There're also browser extensions for local cronjobs like "Tab Auto Refresh" for Firefox

# Attachments and Avatars
After the migration is done, you have to upload the avatars and the attachments:
- Upload the avatars to images/avatars/upload
- Upload the attachments to the files folder. Ensure to turn on FTP Binary Upload first! Else the images will be broken

=> Settings -> Transfer -> Binary

# Cleanup (before a fresh start of the migration)
If you wanna rerun everything, just start the cleanup.php once. 

However, the cleanup does not do a real truncate, the so the id counter
will rise more and more. Since there might be data in the tables already (like in the users table), I recommend to restore the 
tables from above if you want a fresh restart of the migration.

Also the images inside work / images needs to be removed manually.



DURATION: 34 Minutes
