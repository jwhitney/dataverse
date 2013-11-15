# OJS Dataverse Plugin

The [Dataverse Network Project](http://thedata.org/) and the [Public Knowledge Project](http://pkp.sfu.ca/)  are
 partnering to develop plugin that adds data sharing and preservation to the [Open Journal Systems](http://pkp.sfu.ca/ojs/)
 publication process. For more information about the project, visit http://projects.iq.harvard.edu/ojs-dvn/about-project.

## Dataverse Plugin Guide

Refer to the [Dataverse Plugin Guide](https://docs.google.com/document/d/1QgxtxMaWdSZ8gI3wHDkE5EfP4W3M2Za-4DhmX_x3pY0/edit?disco=AAAAAGd77n8#) 
for an overview of data publication workflows supported by the plugin in OJS.

### Installing the plugin

Download the plugin: [dataverse-1.0.0.0.tar.gz](https://drive.google.com/file/d/0B8Zfl4GMgyejdFBKQ3JpNHBtZU0/edit?usp=sharing)

If the SWORD plugin is present in your OJS install, at `plugins/generic/sword`, remove it.
The SWORD plugin uses an earlier version of the swordapp PHP library and **the Dataverse plugin can't be
installed unless it's removed**. 

#### OJS 2.4

Use the web plugin installer available from the journal management pages: click "System Plugins," 
then "Install a New Plugin" to upload the downloaded *.tar.gz file. 

The `plugins` and `lib/pkp/plugins` directories must be web-writable. PHP's `upload_max_filesize` and
`post_max_size` settings must be large enough to allow the plugin source (about 2.3M) to be uploaded. 

After installation, go to "System Plugins" then "Generic Plugins" to enable and configure the 
Dataverse plugin.

#### OJS 2.3

* Unzip the source files and move the `dataverse` directory to `plugins/generic`. 
* Move the SWORD library files from `plugins/generic/dataverse/lib/swordappv2` to 'lib/pkp/plugins/generic/dataverse/swordappv2`
* From the OJS install directory, run `php tools/dbXMLtoSQL.php -schema execute plugins/generic/dataverse/schema.xml` 
to install database tables used by the plugin.
* Enable and configure the plugin as above.

### Using the plugin

The plugin currently supports the following tasks:

* Set up a connection to the Dataverse into which data files will be deposited
* Define a general data policy which is displayed on journal's About page
* Configure terms of use:
    * Define terms of use for depositing or downloading data
    * Opt to fetch terms of use information directly from the configured Dataverse
* Configure metadata options:
    * Choose citation format and type of persistent ID to display in publication citation added to study cataloguing information
* Configure workflow options:
    * require data files with author submissions
    * opt to release studies on acceptance or publication
* Use suppfile form to choose files to be deposited in DV
* Display terms of use in window linked from suppfile form, rather than in the form
* Create draft study on author submission if author has uploaded supplementary files and marked one or more for deposit in the journal's dataverse.
* Populate study cataloguing information with article-level and file-level (type of data, type of data (other), and subject) metadata
* Display data citation in Dataverse options section of suppfile form, if a study exists for the submission
* Display data citation in summary section of author, editor submission review pages
* Delete draft study if editor rejects submission.
* Delete draft study if editor declines submission during the review process.
* Update study cataloguing information when submission metadata is edited
* Delete data file from Dataverse when a suppfile is deleted or when deposit option unticked in suppfile form.
* Add files to study when suppfiles added to completed submission. A study is created if one doesn't already exist.
* Release study based on workflow configuration: on editor approval or article publication
* Display data citation and link to article data files for published articles
* Notify JMs when studies submitted to unreleased DV
* Customize suppfile display in reading tools: suppfiles deposited in DV are linked to corresponding study.
Suppfiles NOT in DV are still available in reading tools as usual.


Still to do:
* Workflow options 
    * require data submissions to be in subsettable formats
    * allow authors to provide persistent link to data
    * embargo options
* Support article-to-study and file-to-study metadata mapping customizations
* Additional publication citation format options for settings form