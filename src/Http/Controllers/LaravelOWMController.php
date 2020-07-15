<?php
namespace Frozenshadow\LaravelOWM\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Frozenshadow\LaravelOWM\LaravelOWM;
use Illuminate\Support\Arr;

class LaravelOWMController extends Controller
{
    /**
     * @var \DateTimeZone
     */
    protected $timezone;

    /**
     * @var LaravelOWM
     */
    protected $lowm;

    /**
     * LaravelOWMController constructor.
     */
    public function __construct()
    {
        $this->timezone = new \DateTimeZone(config('app.timezone'));
        $this->lowm = new LaravelOWM();
    }

    /**
     * Response with the current weather of the requested location/city.
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function currentweather(Request $request)
    {
        $city = $request->get('city');
        $coordinates = $request->get('coord');
        $lang = $request->get('lang', 'en');
        $units = $request->get('units', 'metric');

        if ($city === null && $coordinates == null) {
            abort('400', 'City or coordinates cannot be undefined.');
        }

        $query = $city ?: $coordinates;

        try {
            $current_weather = $this->lowm->getCurrentWeather($query, $lang, $units, true);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage(), 'code' => $e->getCode()]);
        }

        return response()->json(['status' => 'ok', 'data' => $this->processData($current_weather)]);
    }

    /**
     * Response with the forecast of the requested location/city.
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function forecast(Request $request)
    {
        $city = $request->get('city');
        $coordinates = $request->get('coord');
        $lang = $request->get('lang', 'en');
        $units = $request->get('units', 'metric');
        $days = $request->get('days', 5);

        if ($city === null && $coordinates == null) {
            abort('400', 'City or coordinates cannot be undefined.');
        }

        $query = $city ?: $coordinates;

        try {
            $forecast = $this->lowm->getWeatherForecast($query, $lang, $units, $days, true);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage(), 'code' => $e->getCode()]);
        }

        return response()->json(['status' => 'ok', 'data' => $this->processForecastData($forecast)]);
    }

    /**
     * Helper function to process default data.
     *
     * @param object $obj
     * @return array
     */
    private function processData($obj)
    {
        $processedData = [
            'sun' => [
                'rise' => [
                    'date' => $obj->sun->rise->setTimezone($this->timezone)->format('Y-m-d H:i:s'),
                    'timestamp' => $obj->sun->rise->setTimezone($this->timezone)->getTimestamp()
                ],
                'set' => [
                    'date' => $obj->sun->set->setTimezone($this->timezone)->format('Y-m-d H:i:s'),
                    'timestamp' => $obj->sun->set->setTimezone($this->timezone)->getTimestamp()
                ]
            ],
            'lastUpdate' => [
                'date' => $obj->lastUpdate->setTimezone($this->timezone)->format('Y-m-d H:i:s'),
                'timestamp' => $obj->lastUpdate->setTimezone($this->timezone)->getTimestamp()
            ],
        ];

        return array_merge((array) $obj, $processedData);
    }

    /**
     * Helper function to process forecast data.
     *
     * @param \Cmfcmf\OpenWeatherMap\WeatherForecast $forecast
     * @return array
     */
    private function processForecastData($forecast)
    {
        // Create a basic dataset by extracting the forecast.
        // Because this is a private property in the WeatherForecast class,
        // we'll convert the object to an array using reflection.
        // There is probably a better way to do this though... I'm open to ideas.
        $weatherForecastDataset = $this->objectToArray($forecast);
        $basicDataset = Arr::except($weatherForecastDataset, ['forecasts', 'position']);
        $data = $this->processData((object) $basicDataset);

        foreach ($forecast as $obj) {
            $day = $obj->time->day->setTimezone($this->timezone);
            $from = $obj->time->from->setTimezone($this->timezone);
            $to = $obj->time->to->setTimezone($this->timezone);

            $temp = [
                'time' => [
                    'from' => [
                        'date' => $from->format('Y-m-d H:i:s'),
                        'timestamp' => $from->getTimestamp()
                    ],
                    'to' => [
                        'date' => $to->format('Y-m-d H:i:s'),
                        'timestamp' => $to->getTimestamp()
                    ],
                    'day' => [
                        'date' => $day->format('Y-m-d H:i:s'),
                        'timestamp' => $day->getTimestamp()
                    ],
                ]
            ];

            if (isset($last_day)) {
                if ($day->format('Y-m-d H:i:s') == $last_day) {
                    // ISO-8601 numeric representation of the day of the week
                    // 1 (for Monday) through 7 (for Sunday)
                    $day_key = $day->format('N');

                    // The OWM API returns 3 hour forecast data, it means for each day you requested you'll
                    // get weather data each 3 hours in this order:
                    // 06:00 - 09:00, 09:00 - 12:00, 12:00 - 15:00, 15:00 - 18:00, 18:00 - 21:00, 21:00 - 00:00.
                    // So to maintain a well-ordered info I built a key depending on the hours range
                    // (ie: ['06-09'] => [ ... ]).
                    $time_key = $from->format('H').'-'.$to->format('H');

                    $data['days'][$day_key][$time_key] = array_merge($temp, $this->processData($obj));
                }
            }

            $last_day = $day->format('Y-m-d H:i:s');
        }

        return $data;
    }

    /**
     * Helper method for converting an object to an array using reflection
     *
     * @param object $object
     * @return array|bool
     */
    private function objectToArray($object)
    {
        if (!is_object($object)) {
            return false;
        }

        $reflection = new \ReflectionObject($object);
        $properties = array();

        foreach ($reflection->getProperties() as $property) {
            $property->setAccessible(true);
            $properties[$property->getName()] = $property->getValue($object);
        }

        return array_merge((array) $reflection->getConstants(), $properties);
    }
}
