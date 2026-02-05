<?php

namespace Balikovna\Controllers;

use Balikovna\Models\Branch;
use Balikovna\Models\Day;
use Balikovna\Models\OpeningHour;
use Slim\Http\Request;
use Slim\Http\Response;
use Illuminate\Database\Capsule\Manager as Capsule;

class ServiceController extends Controller
{
    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function syncData($request, $response)
    {
        $xml = simplexml_load_file($this->container["settings"]["source_xml"], null, LIBXML_NOCDATA);
        $json = json_encode($xml);
        $array = json_decode($json, true);

        if ($array) {
            Capsule::select("DELETE FROM days;");
            Capsule::select("ALTER TABLE days AUTO_INCREMENT = 1;");

            Capsule::select("DELETE FROM opening_hours;");
            Capsule::select("ALTER TABLE opening_hours AUTO_INCREMENT = 1;");

            Capsule::select("DELETE FROM branches;");
            Capsule::select("ALTER TABLE branches AUTO_INCREMENT = 1;");

            $this->createDays();

            foreach ($array["row"] as $item) {
                $branch = new Branch();
                $branch->zip = $item["PSC"];
                $branch->name = $item["NAZEV"];
                $branch->address = $item["ADRESA"];
                $branch->kind = $item["TYP"];
                $branch->lat = $item["SOUR_X"];
                $branch->lng = $item["SOUR_Y"];
                $branch->city = $item["OBEC"];
                $branch->city_part = $item["C_OBCE"];
                $branch->save();

                foreach ($item["OTEV_DOBY"] as $days) {
                    foreach ($days as $day) {
                        $dayName = $day["@attributes"]["name"];
                        $selectedDay = Day::where('name', $dayName)->first();
                        if (key_exists("od_do", $day)) {
                            if (key_exists("od", $day["od_do"])) {
                                $from = $day["od_do"]["od"];
                                $to = $day["od_do"]["do"];
                                $this->saveOpeningHour($branch, $selectedDay, $from, $to);
                            } else {
                                foreach ($day["od_do"] as $opening) {
                                    $from = $opening["od"];
                                    $to = $opening["do"];
                                    $this->saveOpeningHour($branch, $selectedDay, $from, $to);
                                }
                            }
                        }
                    }
                }

            }
            return $response->withJson(["status" => "OK"], 200);
        } else {
            return $response->withJson(["status" => "ERROR"], 200);
        }

    }

    private function saveOpeningHour($branch, $day, $from, $to)
    {
        $openingHour = new OpeningHour();
        $openingHour->branch_id = $branch->id;
        $openingHour->day_id = $day->id;
        $openingHour->open_from = $from;
        $openingHour->open_to = $to;
        $openingHour->save();
    }

    private function createDays()
    {
        $days = ["Pondělí", "Úterý", "Středa", "Čtvrtek", "Pátek", "Sobota", "Neděle"];
        foreach ($days as $day) {
            $d = new Day();
            $d->name = $day;
            $d->save();
        }
    }
}