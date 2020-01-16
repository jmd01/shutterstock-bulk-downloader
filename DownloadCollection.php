<?php

use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;

class DownloadCollection
{
    /**
     * @var RemoteWebDriver
     */
    protected $driver;

    /**
     * @var string
     */
    protected $shutterstockUsername;

    /**
     * @var string
     */
    protected $shutterstockPassword;

    /**
     * @var int
     */
    protected $collectionId;

    /**
     * @var int
     */
    protected $subscriptionId;

    /**
     * @var string
     */
    protected $csv = '';


    public function __construct($options)
    {
        $host = array_key_exists('host', $options) ? $options['host'] : 'http://localhost:4444/wd/hub';
        $capabilities = array_key_exists('capabilities', $options) ? DesiredCapabilities::$options['capabilities']() : DesiredCapabilities::chrome();
        $connectionTimeout = array_key_exists('connectionTimeout', $options) ? $options['connectionTimeout'] : 5000;

        if (array_key_exists('shutterstockUsername', $options)) {
            $this->shutterstockUsername = $options['shutterstockUsername'];
        } else {
            throw new Exception('shutterstockUsername option is required');
        }
        if (array_key_exists('shutterstockPassword', $options)) {
            $this->shutterstockPassword = $options['shutterstockPassword'];
        } else {
            throw new Exception('shutterstockPassword option is required');
        }
        if (array_key_exists('collectionId', $options)) {
            $this->collectionId = $options['collectionId'];
        } else {
            throw new Exception('collectionId option is required');
        }
        if (array_key_exists('subscriptionId', $options)) {
            $this->subscriptionId = $options['subscriptionId'];
        } else {
            throw new Exception('subscriptionId option is required');
        }

        $this->driver = RemoteWebDriver::create($host, $capabilities, $connectionTimeout);
    }

    public function go()
    {
        $this->login();
        $this->gotoCollectionPage();
        $this->processPages();
        $this->saveCsv();
    }

    protected function login()
    {
        $this->driver->get('https://accounts.shutterstock.com/login/');
        $this->driver->findElement(WebDriverBy::id("login-username"))->sendKeys($this->shutterstockUsername);
        $this->driver->findElement(WebDriverBy::id("login-password"))->sendKeys($this->shutterstockPassword);
        $this->driver->findElement(WebDriverBy::id("login"))->submit();

        // wait until the page is loaded
        $this->driver->wait()->until(
            WebDriverExpectedCondition::titleContains('Profile')
        );
    }

    protected function gotoCollectionPage()
    {
        $this->driver->get('https://www.shutterstock.com/collections/' . $this->collectionId);

        // wait until the cookie banner is loaded then click accept
        //$driver->wait(5, 1000)->until(
        //    WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id('adroll_consent_accept'))
        //);
        // Above not working, go for the ugly option ->>
        sleep(5);
        $cookieBanner = $this->driver->findElements(WebDriverBy::id('adroll_consent_accept'));
        if (count($cookieBanner) > 0) {
            $cookieBanner[0]->click();
        }

    }


    protected function processPages()
    {
        $thumbnails = $this->driver->findElements(WebDriverBy::className('thumbnail-gallery'));
//        var_dump($thumbnails);
        foreach ($thumbnails as $key => $thumbnail) {

            $action = $this->driver->action();
            $action->moveToElement($thumbnail)->perform();

            $thumbnailATag = $thumbnail->findElement(WebDriverBy::className('thumbnail-letterbox'));
            $original_image_url = $thumbnailATag->getAttribute('href');

            // Get everything after final slash
            preg_match("/[^\/]+$/", $original_image_url, $nameFromUrl);
            $nameFromUrl = $nameFromUrl[0];

            // Get everything after final dash
            preg_match("/[^-]+$/", $nameFromUrl, $id);
            $svg = 'shutterstock_' . $id[0] . '.svg';
            $original_image = 'shutterstock_' . $id[0] . '.jpg';

            // Get everything before final dash
            preg_match("/(.*)\-/", $nameFromUrl, $nameWithDashes);
            // Replace dashes with spaces and upper case first character
            $name = ucfirst(preg_replace('/-/', ' ', $nameWithDashes[1]));

            $this->csv .= "$name,$svg,$original_image,$original_image_url";

            $downloadButton = $thumbnail->findElement(WebDriverBy::className('js-download-button'));
            $redownloadButton = $thumbnail->findElement(WebDriverBy::className('js-redownload-button'));

            if (!strstr($downloadButton->getAttribute('class'), 'hidden')) {
                $this->csv .= ",0\r\n";   // Redownload false
                $this->newDownload($downloadButton);
            } else {
                $this->csv .= ",1\r\n";   // Redownload true
                //$this->reDownload($redownloadButton);
            }

            $downloadStatusIcon = $thumbnail->findElement(WebDriverBy::className('download-status-icon'));
            $this->driver->wait(20, 1000)->until(
                WebDriverExpectedCondition::visibilityOf($downloadStatusIcon)
            );

        }

        $this->tryGoToNextPage();
    }

    protected function tryGoToNextPage()
    {
        $nextPageLink = $this->driver->findElement(WebDriverBy::className('js-pagination-next'));
        $nextPageLinkParentLi = $nextPageLink->findElement(WebDriverBy::xpath("./.."));
        if (strstr($nextPageLinkParentLi->getAttribute('class'), 'disabled')) {
            // close the browser
            $this->driver->quit();
        } else {
            $this->goToNextPage();
            $this->processPages();
        }

    }

    protected function goToNextPage()
    {
        $currentPage = $this->driver->findElement(WebDriverBy::className('pagination-current'))->getAttribute('value');
        $nextPage = $currentPage + 1 ;
        $this->driver->get('https://www.shutterstock.com/collections/' . $this->collectionId . '?sort=newestFirst&perPage=50&page=' . $nextPage);

        // wait until the page is loaded
        $this->driver->wait()->until(
            WebDriverExpectedCondition::urlContains('page=' . $nextPage)
        );
    }

    protected function newDownload($downloadButton)
    {
        $downloadButton->click();

        $this->driver->wait(10, 1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id('download_cta_modal'))
        );
        $modal = $this->driver->findElement(WebDriverBy::id('download_cta_modal'));

        $this->driver->wait(10, 1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::className('js-jpeg-size'))
        );
        $modal->findElement(WebDriverBy::className('js-jpeg-size'))->click();

        $downloadForm = $this->driver->findElement(WebDriverBy::className('js-download-form'));
        $this->driver->wait(10, 1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::className('js-subscription-dropdown-toggle'))
        );
        $downloadForm->findElement(WebDriverBy::className('js-subscription-dropdown-toggle'))->click();

        $this->driver->wait(10, 1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id('subscription-' . $this->subscriptionId))
        );
        $downloadForm->findElement(WebDriverBy::id('subscription-' . $this->subscriptionId))->click();
        sleep(1);

        $downloadForm->submit();

    }

    protected function reDownload($redownloadButton)
    {
        $redownloadButton->click();
    }

    protected function saveCsv()
    {
        $csvHeader = "name,svg,original_image,original_image_url,redownload\r\n";
        $this->csv = $csvHeader . $this->csv;

        file_put_contents('./csvs/' . date("Ymd_His") . '.csv', $this->csv);
        echo $this->csv;
    }
}