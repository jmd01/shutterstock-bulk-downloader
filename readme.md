# Shutterstock bulk image download

Shutterstock standard licenses do not offer a bulk download option. This script using Facebok Webdriver which use Selenium automation to process of downloading all images in a collection

## Prerequisites

####Composer
* Install composer

From project root run:
```
curl -sS https://getcomposer.org/installer | php
```

#### Facebook webdriver
Then install the facebook/webdriver library:
```
php composer.phar install
```

### Shutterstock 
#### Create collection
* Login to Shutterstock and create a collection
* Add images to the collection
#### Get collection ID
* Go to collection page eg https://www.shutterstock.com/collections/123456
* Copy collection ID from url eg 123456
#### Get subscription ID
* Click on an image that you haven't previously downloaded
* You should see a modal window open
* Copy the text for the subscription plan you want to use for all downloads

### Start selenium server
```
java -jar selenium-server-standalone-3.9.1.jar
```

### Start webdriver
* Double click ./chromedriver.exe

### Run script
* Copy run.example.php to run.php
* Add Shutterstock credentials, collection ID and subscription text
* Then execute the run script
```
php run.php
```

#### Result
* Once the script has completed you should have the full collection of images downloaded to your default browser Downloads folder
* A csv will also have been saved to ./csvs/{Ymd_his}.csv 