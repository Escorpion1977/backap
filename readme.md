# **Backap**

Backap is a MySQL database backup manager written in PHP that can be bundled into a PHAR file. Backap aims to simply the process of dumping, restoring and syncing MySQL databases using simple CLI commands.

## **How do I get started?**
You can use Backap in one of three ways:

### As a Phar (Recommended)

You may download a ready-to-use version of Backap as a Phar:
```sh
$ curl -LSs https://tecactus.github.io/backap/installer.php | php
```
The command will check your PHP settings, warn you of any issues, and the download it to the current directory. From there, you may place it anywhere that will make it easier for you to access (such as `/usr/local/bin`) and chmod it to `755`. You can even rename it to just `backap` to avoid having to type the `.phar` extension every time.
```sh
$ backap --version
```
Whenever a new version of the application is released, you can simply run the `update` command to get the latest version:
```sh
$ backap update
```

### As a Global Composer Install

This is probably the best way when you have other tools like phpunit and other tools installed in this way:
```sh
$ composer global require tecactus/backap --prefer-source
```

### As a Composer Dependency

You may also install Backap as a dependency for your Composer managed project:
```sh
$ composer require --dev tecactus/backap
```
or
```json
{
    "require-dev": {
        "tecactus/backap": "~1.0"
    }
}
```
Once you have installed the application, you can run the `help` command to get detailed information about all of the available commands. This should be your go-to place for information about how to use Backap.
```sh
$ backap help
```

## **Available Commands**

### **init**

the `init` command creates a yaml configuration file called `.backap.yaml` into the current directory.
```sh
$ backap init
```
The  `.backap.yaml` file structure is as follows:

#### backap_storage_path (optional)
In this attribute you can define the *path* where all the backup files generated with Backap will be stored.

This path *MUST* be *ABOSULTE* if is defined.

```yaml
backap_storage_path: /absolute/path/to/backups/folder
```
If any path is defined or if you omit this attribute, Backap will create a `storage/database` folder into the current directory.

#### mysqldump_path (optional)
In this attribute you can define the path where `mysqldump` is located.

This path *MUST* be *ABOSULTE* if is defined.
```yaml
mysqldump_path: /path/to/mysqldump
```
If any path is defined or if you omit this attribute, Backap will try to call the globally reference to `mysqldump`.

#### mysql_path (optional)
In this attribute you can define the path where `mysql` is located.

This path *MUST* be *ABOSULTE* if is defined.
```yaml
mysql_path: /path/to/mysql
```
If any path is defined or if you omit this attribute, Backap will try to call the globally reference to `mysql`.

#### timezone (optional)
In this attribute you can define an specific *timezone*, this to know when the backup files were generated.

This timezone *MUST* have a *VALID* name.

For example:
```yaml
timezone: America/Lima
```
If any timezone is defined or if you omit this attribute, Backap will use *UTC*.

#### enable_compression (optional)
Backap generates `.sql` files by default, you can tell Backap to compress the generated backup file enabling compression then Backap will generate `.sql.gz` files.

This value *MUST* be *BOOLEAN* if is defined.
```yaml
enable_compression: true
```
If any value is defined or if you omit this attribute, Backap sets compression as `false` by default.

#### default_connection (mandatory)
Backap needs to know which of your database connections will dump or restore, that's why you have to define a default connection to work on when you do not explicits define one.

The connection name *MUST* be *DECLARED* as an element on the *connections* array attribute.
```yaml
default_connection: myconnection
```

#### connections (mandatory)
Backap can handle multiple database connections at the same time but first you have to define each one and asing them a diferent name.

Each connection *MUST* have *HOSTNAME, DATABASE and USERNAME * declared attribtues.
PORT and PASSWORD are optional.

For example:
```yaml
connections:
  myconnection:
    hostname: 192.168.1.27
    port: 3306
    database: important_db
    username: userdb
    password: supersecretpassword
```
In the example we defined a connection named `myconnection`.
Of course you can define many as you want:
```yaml
connections:

...

  myconnection:
    hostname: 192.168.1.27
    port: 3306
    database: important_db
    username: userdb
    
...

  otherconnection:
    hostname: 177.200.100.9
    port: 3306
    database: other_db
    username: admindb
    password: supersecretpassword
    
...
```

#### cloud (optional)
Backap allows you to sync your backup files with cloud providers as *Dropbox*.
To enable this feature you must declare and array atribute called `cloud` and inside them declare, with a unique name, each of the cloud adapters, as an array too, that will be available to sync.

For example:
```yaml
cloud:
  adapaterone:
    ...    
  adapatertwo:
    ...
```
Each provider requires diferent parameters thats why every adapter required diferent attributes but all of them *MUST* have an *ATTRIBUTE* called `provider`.

For example:
```yaml
cloud:
  adapaterone:
    provider: dropbox
    ...
```

##### Dropbox Adapter

To declare a Dropbox adapter you must define the following attributes:
- **provider** as ***dropbox***
- **access_token** generated on [Dropbox for Developers](https://www.dropbox.com/developers)
- **app_secret** generated on [Dropbox for Developers](https://www.dropbox.com/developers)
- **path** will be the path inside your Dropbox

For example:
```yaml
cloud:
  dropbox:
    provider: dropbox
    access_token: your_access_token
    app_secret: your_secret
    path: /path/on/your/dropbox
```


### **mysql:dump**
The `mysql:dump` command dumps the database for the `default_connection` .
```sh
$ backap mysql:dump
```

#### **--connection, -c**
You can explicit define one or more connections to be dumped
```sh
$ backap mysql:dump --conection myconnection --connection otherconnetion
```
or
```sh
$ backap mysql:dump -c myconnection -c otherconnection
```

#### **--no-compress**
Disable file compression regardless if is enabled in `.backap.yaml` file. This option will be always overwrited by `--compress` option.
```sh
$ backap mysql:dump --no-compress
```

#### **--compress**
Enable file compression regardless if is disabled in `.backap.yaml` file. This option will always overwrite `--no-compress` option.
```sh
$ backap mysql:dump --compress
```

#### **--sync, -s**
You can sync dump files with one or more cloud provider at the moment the dump file is generated. This option will be always overwrited by `--sync-all` option.
```sh
$ backap mysql:dump --sync dropboxone --sync dropboxtwo
```
or
```sh
$ backap mysql:dump -s dropboxone -s dropboxtwo
```

#### **--sync-all, -S**
Also you can sync dump files with all the defined cloud provider at the same time at the moment the dump file is generated. This option will always overwrite `--sync` option.
```sh
$ backap mysql:dump --sync-all
```
or
```sh
$ backap mysql:dump -S
```

### **mysql:restore**
The `mysql:restore` command restores the `default_connection`  database from a backup file.
```sh
$ backap mysql:restore
```
The `mysql:restore` command displays a list of all the backup files available *only* for the connection's database. Latest backup file is selected as default.
Then Backap ask for your confirmation to proceed with the database restoration.

#### **--conection, -c**
You can explicit define the connection name to be restored
```sh
$ backap mysql:restore --conection otherconnetion
```
or
```sh
$ backap mysql:restore -c otherconnection
```

#### **--filename, -f**
You can explicit define the backup file name to be restored
```sh
$ backap mysql:restore --filename mybackupfile.sql
```
or
```sh
$ backap mysql:restore -f mybackupfile.sql
```

#### **--all-backup-files, -A**
The `mysql:restore` command by default displays a list of all the backup files available *only* for the defined connection's database but you can use the `--all-backup-files` option to return a list of all backup file generated by Backap. Latest backup file is selected as default.
```sh
$ backap mysql:restore --all-backup-files
```
or
```sh
$ backap mysql:restore -A

```
#### **--restore-latest-backup , -L**
Explicit restore the latest backup file for the connection's database.
```sh
$ backap mysql:restore --restore-latest-backup 
```
or
```sh
$ backap mysql:restore -L
```

#### **--yes, -y**
The `mysql:restore` command always ask for your confirmation to proceed but you can confirm it without seeing the confirmation prompt using the `--yes` option.
```sh
$ backap mysql:restore --yes
```
or
```sh
$ backap mysql:restore -y
```

#### **--from-cloud, -C**
Display a list of cloud providers where to retrieve backup files.
```sh
$ backap mysql:restore --from-cloud
```
or
```sh
$ backap mysql:restore -C
```

#### **--from-provider, -p**
Explicit define the cloud provider where to retrieve backup files
```sh
$ backap mysql:restore --from-provider dropboxone
```
or
```sh
$ backap mysql:restore -p dropboxone
```

### **files**
The `files` command displays a table with detailed data about all the backup files stored on your `backap_storage_path`
```sh
$ backap files
```

#### **--from-cloud, -C**
Display a list of cloud providers where to retrieve backup files.
```sh
$ backap files --from-cloud
```
or
```sh
$ backap files -C
```

#### **--from-provider, -p**
Explicit define the cloud provider where to retrieve backup files
```sh
$ backap files --from-provider dropboxone
```
or
```sh
$ backap files -p dropboxone
```

### **sync**
The `sync` command allows you to synchronize backup files with cloud providers. Pull files from cloud or push file to remote storage providers.
By default the `sync` asks you to choose a provider from a list of current configured providers but you can explicit define a provider using the `--provider` option.

#### **push**
The `push` action will sync all your backup files stored locally to the remote selected provider.
```sh
$ backap sync push
```
or
```sh
$ backap sync push --provider dropboxone
```
or
```sh
$ backap sync push -p dropboxone
```

#### **pull**
The `pull` action will sync all your backup files stored on the selected provider to your local storage folder.
```sh
$ backap sync pull
```
or
```sh
$ backap sync pull --provider dropboxone
```
or
```sh
$ backap sync pull -p dropboxone
```
