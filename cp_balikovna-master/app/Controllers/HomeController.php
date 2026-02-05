<?php
/**
 * Created by IntelliJ IDEA.
 * User: green
 * Date: 13/07/2017
 * Time: 12:40 PM
 */

namespace Balikovna\Controllers;


use Balikovna\Models\Branch;
use Balikovna\Models\OpeningHour;
use Slim\Http\Request;
use Slim\Http\Response;

class HomeController extends Controller
{
    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function home($request, $response)
    {
        return $this->view->render($response, "index.twig", [
            "branches" => Branch::all(),
        ]);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function openingHours($request, $response, $args)
    {
        $branchId = $args["branchId"];
        $hours = OpeningHour::where("branch_id", $branchId)
            ->orderBy("day_id")
            ->orderBy("open_from")
            ->get();

        $days = [];
        foreach ($hours as $hour) {
            if (!key_exists($hour->day->name, $days)) {
                $days[$hour->day->name] = [];
            }
            $days[$hour->day->name][] = [
                "from" => $hour->open_from,
                "to" => $hour->open_to,
            ];
        }

        return $this->view->render($response, "_opening_hours.twig", [
            "days" => $days,
        ]);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function branches($request, $response)
    {
        return $response->withJson(["branches" => Branch::searchBranches($request->getParam('q'))], 200);
    }
}