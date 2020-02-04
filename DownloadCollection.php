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
    protected $subscriptionText;

    /**
     * @var string
     */
    protected $csv = '';

    /**
     * @var int
     */
    protected $currentPage = 1;


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
        if (array_key_exists('subscriptionText', $options)) {
            $this->subscriptionText = $options['subscriptionText'];
        } else {
            throw new Exception('subscriptionText option is required');
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
        $this->driver->get('https://www.shutterstock.com/collections/' . $this->collectionId . '?sort=newestFirst&perPage=50&page=' . $this->currentPage);

        // wait until the cookie banner is loaded then click accept
//        sleep(5);
//        $cookieBanner = $this->driver->findElements(WebDriverBy::id('adroll_consent_accept'));
//        if (count($cookieBanner) > 0) {
//            $cookieBanner[0]->click();
//        }

    }


    protected function processPages()
    {
        $thumbnails = $this->driver->findElements(WebDriverBy::cssSelector('a[data-automation="CollectionGrid_item_link"]'));

        foreach ($thumbnails as $key => $thumbnail) {

            $action = $this->driver->action();
            $action->moveToElement($thumbnail)->perform();

            $original_image_url = $thumbnail->getAttribute('href');
            $id = $thumbnail->getAttribute('name');

            $svg = 'shutterstock_' . $id[0] . '.svg';
            $original_image = 'shutterstock_' . $id[0] . '.jpg';


            $name = $thumbnail->findElement(WebDriverBy::cssSelector('div[data-automation="CollectionGrid_item"]'))->getAttribute('alt');

            $this->csv .= "$name,$svg,$original_image,$original_image_url";

            $downloadButton = $thumbnail->findElement(WebDriverBy::cssSelector('button[data-automation="CollectionGridItemOverlay_download"]'));

            if ($downloadButton->getText() == 'Redownload') {
                $this->csv .= ",1\r\n";   // Redownload true
            } else {
                $this->csv .= ",0\r\n";   // Redownload false
                $this->newDownload($downloadButton);
            }

        }

        $this->tryGoToNextPage();
    }

    protected function tryGoToNextPage()
    {
        $nextPageLink = $this->driver->findElement(WebDriverBy::cssSelector('button[data-track-label="nextPage"]'));

        if (strstr($nextPageLink->getAttribute('disabled'), true)) {
            // close the browser
            $this->driver->quit();
        } else {
            $this->goToNextPage();
            $this->processPages();
        }

    }

    protected function goToNextPage()
    {
        $this->currentPage++;
        $nextPage = $this->currentPage;
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
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::xpath("//button[contains(text(),'Confirm download')]"))
        );
        $this->driver->findElement(WebDriverBy::xpath("//span[contains(text(),'" . $this->subscriptionText . "')]"))->click();
        sleep(1);
        $this->driver->findElement(WebDriverBy::xpath("//button[contains(text(),'Confirm download')]"))->click();

        $this->driver->wait(20, 1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::xpath("//a[contains(text(),'Back to search')]"))
        );
        $this->driver->findElement(WebDriverBy::xpath('//span[@data-icon="close"]/parent::button'))->click();

        sleep(2);

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