<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Terminalbd\KpiBundle\Controller;



use App\Entity\Core\Setting;
use App\Entity\Core\SettingType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Terminalbd\KpiBundle\Entity\EmployeeBoard;

/**
 * @Route("/kpi/statistics")
 * @author Md Shafiqul Islam <shafiqabs@gmail.com>
 * @Security("is_granted('ROLE_KPI_ADMIN') or is_granted('ROLE_DEVELOPER')")
 */
class KpiStatisticsController extends AbstractController
{
    /**
     * @Route("/", name="kpi_statistics_index")
     */
    public function index()
    {

        $month = date('m');
//        $month = 2;
        $year = $month === 1 ? date('Y')-1 : date('Y');

        $prevMonthNum  = $month != 1 ? $month-1 : 12 ;
        $dateObj   = \DateTime::createFromFormat('!m', $prevMonthNum);
        $previousMonth = $dateObj->format('F');

        $formatWiseGradeNumberKpi = [];
        $selectedFormat = null;

        $settingType = $this->getDoctrine()->getRepository(SettingType::class)->findOneBy(['slug' => 'report-mode', 'status' => 1]);
        $reportFormat = $this->getDoctrine()->getRepository(Setting::class)->findBy(['settingType' => $settingType, 'status' => 1]);
        
        return $this->render('@TerminalbdKpi/statistics/index.html.twig',[
            'reportFormat' => $reportFormat,
            'month' => $previousMonth,
            'year' => $year,
        ]);
    }


    /**
     * @Route("/kpi-statistics/refresh",methods={"POST"}, name="kpi_statistics_refresh", options={"expose" = true})
     * @param Request $request
     * @return Response
     */
    public function kpiStatisticsRefresh(Request $request)
    {
        $selectedFormat = $request->request->get('reportFormatId');
//        $monthYear = explode(',', $request->request->get('monthYear'));
        $startMonthYear = explode(',', $request->request->get('startMonthYear'));
        $endMonthYear = explode(',', $request->request->get('endMonthYear'));

        $startMonth = $startMonthYear[0];
        $startYear = $startMonthYear[1];

        $endMonth = $endMonthYear[0];
        $endYear = $endMonthYear[1];

        $start    = (new \DateTime($startMonth.'-'.$startYear))->modify('first day of this month');
        $end      = (new \DateTime($endMonth.'-'.$endYear))->modify('first day of next month');
        $interval = \DateInterval::createFromDateString('1 month');
        $period   = new \DatePeriod($start, $interval, $end);

        $months = [];
        foreach ($period as $dt) {
            array_push($months, $dt->format("F"));
        }
        $months = array_unique($months);

        $years = [];

        for ($startYear; $startYear <= $endYear; $startYear++){
            array_push($years, $startYear);
        }

        $formatWiseGradeNumberKpi = $this->getDoctrine()->getRepository(EmployeeBoard::class)->getFormatWiseGradeNumberKpi($selectedFormat, $months, $years);
//        $formatWiseGradeNumberKpi = $this->getDoctrine()->getRepository(EmployeeBoard::class)->getFormatWiseGradeNumberKpi($reportFormatId, $monthYear[0], $monthYear[1]);

        if ($formatWiseGradeNumberKpi){
            $html = $this->renderView('@TerminalbdKpi/statistics/inc/_kpi-statistics-refresh.html.twig',[
                'formatWiseGradeNumberKpi' => $formatWiseGradeNumberKpi,
            ]);
        }else{
            $html = '';
        }
        return new JsonResponse(['html' => $html]);
    }

    /**
     * @Route("/kpi-statistics-top-ten/refresh",methods={"POST"}, name="kpi_statistics_top_ten_refresh", options={"expose" = true})
     * @param Request $request
     * @return Response
     */
    public function kpiStatisticsTopTenRefresh(Request $request)
    {
        $topTenPeopleFormatSlug = $request->request->get('topTenPeopleFormatSlug');
        $startMonthYear = explode(',', $request->request->get('startMonthYear'));
        $endMonthYear = explode(',', $request->request->get('endMonthYear'));

        $startMonth = $startMonthYear[0];
        $startYear = $startMonthYear[1];

        $endMonth = $endMonthYear[0];
        $endYear = $endMonthYear[1];

        $start    = (new \DateTime($startMonth.'-'.$startYear))->modify('first day of this month');
        $end      = (new \DateTime($endMonth.'-'.$endYear))->modify('first day of next month');
        $interval = \DateInterval::createFromDateString('1 month');
        $period   = new \DatePeriod($start, $interval, $end);

        $months = [];
        foreach ($period as $dt) {
            array_push($months, $dt->format("F"));
        }
        $months = array_unique($months);

        $years = [];

        for ($startYear; $startYear <= $endYear; $startYear++){
            array_push($years, $startYear);
        }

        $topTenPeople = $this->getDoctrine()->getRepository(EmployeeBoard::class)->getRankingPeople($topTenPeopleFormatSlug, $months, $years, 'top');

        if ($topTenPeople){
            $html = $this->renderView('@TerminalbdKpi/statistics/inc/_kpi-statistics-top-10-refresh.html.twig',[
                'topTenPeople' => $topTenPeople,
            ]);
        }else{
            $html = '';
        }

        return new JsonResponse(['html' => $html]);
    }
    
    /**
     * @Route("/kpi-statistics-bottom-ten/refresh",methods={"POST"}, name="kpi_statistics_bottom_ten_refresh", options={"expose" = true})
     * @param Request $request
     * @return Response
     */
    public function kpiStatisticsBottomTenRefresh(Request $request)
    {
        $bottomTenPeopleFormatSlug = $request->request->get('bottomTenPeopleFormatSlug');
        $startMonthYear = explode(',', $request->request->get('startMonthYear'));
        $endMonthYear = explode(',', $request->request->get('endMonthYear'));

        $startMonth = $startMonthYear[0];
        $startYear = $startMonthYear[1];

        $endMonth = $endMonthYear[0];
        $endYear = $endMonthYear[1];


        $start    = (new \DateTime($startMonth.'-'.$startYear))->modify('first day of this month');
        $end      = (new \DateTime($endMonth.'-'.$endYear))->modify('first day of next month');
        $interval = \DateInterval::createFromDateString('1 month');
        $period   = new \DatePeriod($start, $interval, $end);

        $months = [];
        foreach ($period as $dt) {
            array_push($months, $dt->format("F"));
        }
        $months = array_unique($months);

        $years = [];

        for ($startYear; $startYear <= $endYear; $startYear++){
            array_push($years, $startYear);
        }

        $bottomTenPeople = $this->getDoctrine()->getRepository(EmployeeBoard::class)->getRankingPeople($bottomTenPeopleFormatSlug, $months, $years, 'bottom');

        if ($bottomTenPeople){
            $html = $this->renderView('@TerminalbdKpi/statistics/inc/_kpi-statistics-bottom-10-refresh.html.twig',[
                'bottomTenPeople' => $bottomTenPeople,
            ]);
        }else{
            $html = '';
        }

        return new JsonResponse(['html' => $html]);

    }

}
