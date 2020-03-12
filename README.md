# Overview

This is codebase for an online ordering API. It is written in PHP and the master of all things that have to do with the transactional functionality of online ordering. we make absolutely no guarentees to its performance and reliability.

## Requirements

To install Homebrew visit the
* http://brew.sh/

## Configure files for the environment

### Add the app2 symlink

Add a symbolic link at the top level of the codebase that points to the top level and is named `app2`:
```
ln -s . app2
```

### Add the /usr/local/ordering directory

`/usr/local/ordering` should contain some files that the tests and environment depend on. It may be easiest to grab the files from someone next to you, but the structure should look like this:

```
.
├── etc
│   └── ordering_database.conf
└── httpd
    └── htdocs
        ├── git_repos.zip
        └── prod
            └── app2 -> /Users/kendall/code/ordering/app2
```

(Obviously, the path should point to your copy of the codebase.)

### Make sure the scratch folder has the write permissions

There is a folder in the ordering directory named `scratch`. It's permissions need to be `777`.

```
chmod -R 777 scratch
```

## PHP

### Tap

Tap the homebrew repository that has PHP versions.

    brew tap homebrew/php

### Installation PHP 7.0 and mcrypt support

    brew install php70 php70-mcrypt

Add the installation path in ~/.bashrc:

     /usr/local/Cellar/php70/......

Verify the PATH contains that `/usr/local/Cellar/php70/7.0/bin` with the command:

    cat ~/.bashrc

#### Update php.ini file

Change the value setting of error_reporting:  

    error_reporting = E_ALL & E_NOTICE & E_STRICT & ~E_DEPRECATED

Restart the apache server for reload the settings

          sudo apachectl start
          
### Install PhpUnit

            brew install phpunit
            
### Install composer

            brew install composer

## MySQL

### Installation

The current version used in production is 5.5.x. If using a Mac, the easiest setup is to use the disk image installation.

* Visit the [MySQL Community Server download page](http://dev.mysql.com/downloads/mysql/)
* Click on "Looking for previous GA versions?"
* From the dropdown list, select 5.5.45 (or whichever 5.5.x version is available)
* Download the DMG for Mac OS X 10.9 (currently the download points here: http://dev.mysql.com/downloads/file.php?id=458244)
* Mount the disk image and click through the installation wizard.


### Start the MySQL server

Open the MySQL preference pane from System Preferences and click the button to start the MySQL server.

### Ensure mysql is on the path

Make sure `mysql` is on your path by running `mysql -u root`. If not, add it in your `.bashrc`. The line should look like so: `export PATH=$PATH:/usr/local/mysql/bin`

### Configure the ordering-specific users

We will need the users to have full access to the database. Because this is development, it's generally safe to go ahead and add the access to all of the localhost databases. You will need to use the same password for these users as we use in the production. It's the same one. Ask someone who knows for the password...

    CREATE USER 'mainapiuser'@'localhost' IDENTIFIED BY 'therealpasswordgoeshere';
    CREATE USER 'mainapiuser'@'%' IDENTIFIED BY 'therealpasswordgoeshere';
    CREATE USER 'mainapiuseradmin'@'localhost' IDENTIFIED BY 'therealpasswordgoeshere';
    CREATE USER 'mainapiuseradmin'@'%' IDENTIFIED BY 'therealpasswordgoeshere';

Now, we need to grant the necessary privileges to those users.    
    
    GRANT ALL ON *.* TO 'mainapiuser'@'localhost';
    GRANT ALL ON *.* TO 'mainapiuser'@'%';
    GRANT ALL ON *.* TO 'mainapiuseradmin'@'localhost';
    GRANT ALL ON *.* TO 'mainapiuseradmin'@'%';

## Configure Apache

The base installation of Apache on Max OS X works fine. It is currently version 2.4.16.

### Configuration

We need to edit the Apache configuration file such that it serves the ordering codebase. There are a number of ways to do this, but I did it the following way.

* Open your `/etc/apache2/httpd.conf` file in a text editor.
* Configure your document root to be the path to the ordering codebase. Each path will be different based on the machine. Find the `DocumentRoot` line in the file. It should be immediately followed by a `Directory` line. Edit these 2 lines with the correct path. Mine looks like this:


    DocumentRoot "/Users/kendall/code/ordering"
    <Directory "/Users/kendall/code/ordering">
    
* This same `Directory` block also needs to allow `Override`s. My Directory block is as follows:

```
<Directory "/Users/kendall/code/ordering">
      #
      # Possible values for the Options directive are "None", "All",
      # or any combination of:
      #   Indexes Includes FollowSymLinks SymLinksifOwnerMatch ExecCGI MultiViews
      #
      # Note that "MultiViews" must be named *explicitly* --- "Options All"
      # doesn't give it to you.
      #
      # The Options directive is both complicated and important.  Please see
      # http://httpd.apache.org/docs/2.2/mod/core.html#options
      # for more information.
      #
      Options Indexes FollowSymLinks MultiViews

      #
      # AllowOverride controls what directives may be placed in .htaccess files.
      # It can be "All", "None", or any combination of the keywords:
      #   Options FileInfo AuthConfig Limit
      #
      AllowOverride All

      #
      # Controls who can get stuff from this server.
      #
      Order allow,deny
      Allow from all
</Directory>
```

* Make sure that mod_rewrite is enabled. Find this line in the file and make sure it is not commented. `LoadModule rewrite_module libexec/apache2/mod_rewrite.so`
* Make sure that the `php5_module` is enabled and pointing to PHP5.3 that you installed previously: `LoadModule php5_module /usr/local/opt/php53/libexec/apache2/libphp5.so`. Make sure that any other lines starting with `LoadModule php5_module` are commented out.
* You *may* need to also edit your `/etc/apache2/extra/vhosts` file. I have a virtual host configured that looks like this. (Your paths will differ.)


```
<VirtualHost *:80>
    DocumentRoot "/Users/kendall/Sites/ordering"
    Options Indexes FollowSymLinks MultiViews
    # ServerAdmin webmaster@dummy-host.example.com
    # ServerName dummy-host.example.com
    # ServerAlias www.dummy-host.example.com
    # ErrorLog "/private/var/log/apache2/dummy-host.example.com-error_log"
    # CustomLog "/private/var/log/apache2/dummy-host.example.com-access_log" common
    <Directory /Users/kendall/Sites/ordering>
        AllowOverride All
        Order allow,deny
        Allow from all
        
        
        <IfModule mod_rewrite.c>
        RewriteEngine On
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_URI} !smaw_dispatch\.php$
        RewriteCond %{REQUEST_URI} !smaw_v2_dispatch\.php$
        RewriteCond %{REQUEST_URI} !smaw_message_dispatch\.php$
        RewriteCond %{REQUEST_URI} !smaw_admin_dispatch\.php$
        #RewriteCond %{REQUEST_URI} !smaw_order_dispatch\.php$
        RewriteRule .*admin.* smaw_admin_dispatch.php [L,QSA]
        RewriteRule .*messagemanager.* smaw_message_dispatch.php [L,QSA]
        RewriteRule .*ordermanager.* smaw_message_dispatch.php [L,QSA]
        RewriteRule .*phone.* smaw_dispatch.php [L,QSA]
        RewriteRule .*apiv2.* smaw_v2_dispatch.php [L,QSA]
        </IfModule>
        
    </Directory>
</VirtualHost>
```

* Restart apache: `sudo apachectl restart`

## And Finally

Your environment should now be up and running. You should now be able to run the tests etc.

### Run -master Unit tests
Before run all unit test, clean the Database with:

    phpunit unit_tests/AADataCleanTest.php

#### Notes

* If the AADataCleanTest fails, you can check if you have a good connection to the database with:
```
~/vendor/bin/phinx migrate
```

* Validate that you can connect to mysql with the mainapiuser user. (Ask someone who knows for the password.)
```
mysql -u mainapiuser -h 127.0.0.1 -p test_db
mysql -u mainapiuser -h localhost -p test_db
```

  * It is possible that your version of MySQL actually runs on port 3307. In this case, you will need to update the `phinx.yml` file to use port 3307 in the `development` environment. To test this, see if you can connect with:
  ```
  mysql -u mainapiuser -h 127.0.0.1 -P 3307 -p test_db
  ```

* Verify that all users have write permissions to lib/utilities/cache.storage
```
chmod -R 777 lib/utilities/cache.storage
```

* Clean the database with:
```
phpunit unit_tests/AADataCleanTest.php 2> /tmp/test-output.log
```

* Run all tests with:
```
phpunit unit_tests 2> /tmp/test-output.log
```

* If the `DispatchTest.php` unit test file has failures, check the file permissions of the `lib/utilities/cache.storage` directory to make sure you have read/write access.

* If Apache looks like it is failing to start, check the configuration with `apachectl configtest`.

# Connecting to the RDS databases

