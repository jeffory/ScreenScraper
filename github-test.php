<?php
    error_reporting(E_ALL);
    require_once 'screenscraper.php';

    $scraper = new ScreenScraper;

    if ($data = $scraper->retrievePage('https://status.github.com/'))
    {
        $services = $data->findByXpath('//div[@class="service"]');

        $statuses = array();

        foreach ($services as $service)
        {
            $name = $data->findByXpath('//span[@class="name"]', $service);
            $status = $data->findByXpath('//span[contains(concat(" ", normalize-space(@class), " "), " status ")]', $service);

            $statuses[$name] = $status;
        }

        print_r($statuses);
    }
    else
    {
        echo 'Error: '. $scraper->request_error;
    }
