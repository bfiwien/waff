<?php

class waffClient
{
    /**
     * The active Soap connection to WAFF.
     *
     * @var SoapClient
     */
    protected $client = null;

    /**
     * Token to communicate with SoapClient.
     *
     * @var string
     */
    protected $token = '';

    /**
     * The offer number we want to interact with.
     *
     * @var string
     */
    protected $OfferNumber = '';

    /**
     * Create a new WAFF instance.
     *
     * @param string $username
     * @param string $password
     */
    public function __construct($username = '', $password = '')
    {
        $this->client = new SoapClient('http://service.weiterbildung.at/Version1_2.asmx?WSDL');

        $this->token = $this->call('Login', array('username' => $username, 'password' => $password));
    }

    /**
     * Pass dynamic methods onto the call methode.
     *
     * @param string $name
     * @param array  $arguments
     *
     * @return string
     */
    public function __call($name, $arguments)
    {
        $withOfferNumber = (count($arguments) == 2);

        return $this->call($name, $arguments[0], $withOfferNumber);
    }

    /**
     * Pass request onto the WAFF instance.
     *
     * @param string $methode
     * @param array|null  $arguments
     * @param bool   $withOfferNumber
     *
     * @return string
     */
    protected function call($methode, $arguments = array(), $withOfferNumber = false)
    {
        $arguments['token'] = $this->token;
        if ($withOfferNumber) {
            $arguments['OfferNumber'] = $this->OfferNumber;
        }

        $request = call_user_func_array(array($this->client, $methode), array($arguments));

        $response = array_values(get_object_vars($request));
        $response = $response[0];

        if ($response->ResultCode != 0) {
            throw new Exception($response->ErrorMessage, $response->ResultCode);
        }

        return $response->ReturnValue;
    }

    /**
     * Sets the offer number.
     *
     * @param string $number
     *
     * @return $this
     */
    public function setOfferNumber($number)
    {
        $this->OfferNumber = $number;

        return $this;
    }

    /**
     * Add or update an offer.
     *
     * @param array $attributes
     *
     * @return $this
     */
    public function setOffer($attributes = array())
    {
        $this->OfferNumber = $attributes['OfferNumber'];

        $this->call('updateOffer', $attributes);

        return $this;
    }

    /**
     * Add or update the specification for an offer.
     *
     * @param array $attributes
     *
     * @return $this
     */
    public function setSpecification($attributes = array())
    {
        $this->call('updateSpecification', $attributes, true);

        return $this;
    }

    /**
     * Clear all dates for an offer.
     *
     * @return $this
     */
    public function clearDates()
    {
        $this->call('clearDates', null, true);

        return $this;
    }

    /**
     * Clear all dates and adds new dates for an offer.
     *
     * @return $this
     */
    public function setDates()
    {
        $this->clearDates();

        foreach (func_get_args() as $date) {
            $this->call('addDate', array(
              'Start' => $this->formatDate($date['start']),
              'End' => $this->formatDate($date['end']),
              'idLocation' => $this->getLocation($date['location']),
            ), true);
        }

        return $this;
    }

    /**
     * Add a single date for an offer.
     *
     * @param string $start
     * @param string $end
     * @param mixed  $location
     *
     * @return $this
     */
    public function setDate($start, $end, $location = 0)
    {
        $this->setDates(array('start' => $start, 'end' => $end, 'location' => $location));

        return $this;
    }

    /**
     * Clear all themes for an offer.
     *
     * @return $this
     */
    public function clearThemes()
    {
        $this->call('clearThemes', null, true);

        return $this;
    }

    /**
     * Clear all themes and adds new themes for an offer.
     *
     * @return $this
     */
    public function setThemes()
    {
        $this->clearThemes();

        foreach (func_get_args() as $args) {
            $args = (is_array($args)) ? $args : array($args);

            foreach ($args as $theme) {
                $this->call('addTheme', array('Theme' => $theme), true);
            }
        }

        return $this;
    }

    /**
     * location lookup.
     *
     * @param mixed $term
     *
     * @return int
     */
    public function getLocation($term = null)
    {
        if ($term === 0) {
            return 0;
        }

        try {
            return $this->call('getLocationByXID', array('externalID' => (is_array($term)) ? $term['externalID'] : $term));
        } catch (Exception $e) {
          //Can't find location by id
        }

        try {
            return $this->call('getLocation', array('LocationName' => (is_array($term)) ? $term['LocationName'] : $term));
        } catch (Exception $e) {
          //Can't find location by name
        }

        return 0;
    }

    /**
     * Creates a new location if it is not found.
     *
     * @param array $attributes
     *
     * @return int locationId|0
     */
    public function findOrNewLocation($attributes = array())
    {
        try {
            if ($id = $this->getLocation($attributes)) {
                return $id;
            }
        } catch (Exception $e) {
          //Can't find an existing location.

          return $this->call('addLocation', $attributes);
        }


    }

    /**
     * Brings the date to the correct format.
     *
     * @param string $date
     *
     * @return string
     */
    protected function formatDate($date)
    {
        return date('d.m.Y H:i', strtotime($date));
    }
}
