# roundcube-sms-login-otp
This is a plugin which works with sms gateway clickatell, to secure the login page with sms otp.

INSTALL INSTRUCTIONS AND PLUGIN ACTIVATION:

Register/create account on clickatell.com and create an API code (for sms messages-channels) and add some money to the account, create a test phone and try if the sms gateway works. If it works copy the API key and put the gateway to "production" mode.

Upload the files to the plugin folder (login_otp), enable it i in the config.inc.php.

Now you need to edit the plugins config file, replace/add the API key in the 1st column, and leave the "sender-id" blank!!!

Now you need to edit the database of roundcube (phpMyAdmin):

Adjust the table name as needed.

ALTER TABLE `rcz6_users` ADD COLUMN otp_number VARCHAR(15) AFTER failed_login_counter;

Add prefix to user where applicable, for example:
ALTER TABLE `rcz6_users`

Thats all,
now you need to login to your mail (Roundcube), and go to Settings-->Server settings and enable the otp plugin.

On next login you need to enter your number, and on the next (second) login you will receive a sms message with the otp code (6 numbers).

Thats all.

ATTENTION: THE PLUGIN AND MY GATEWAY ARE SET TO USE ONLY SLOVENIAN NUMBERS (+386), YOU CAN EDIT THE CODE AS NEDDED!!!

