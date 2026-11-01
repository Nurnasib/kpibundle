<?php

namespace Terminalbd\KpiBundle\Controller\kpiReport;

use App\Entity\User;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Terminalbd\KpiBundle\Entity\EmployeeBoard;
use Terminalbd\KpiBundle\Entity\EmployeeBoardAttribute;
use Terminalbd\KpiBundle\Form\SalesReportFilterFormType;

/**
 * One employee's KPI marks over a month range: one column group per KPI item,
 * one sub-column per month plus a range total.
 *
 * Access mirrors KpiReportController; the employee dropdown (SalesReportFilterFormType)
 * already limits non-KPI-admins to their own direct reports.
 *
 * @Route("/kpi/report")
 * @Security("is_granted('ROLE_KPI_ADMIN') or is_granted('ROLE_DOMAIN') or is_granted('ROLE_LINE_MANAGER')")
 */
class EmployeeKpiMonthRangeController extends AbstractController
{
    private const MONTHS = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December',
    ];

    /**
     * @Route("/employee-kpi-month-range", name="kpi_employee_month_range_report")
     */
    public function index(Request $request): Response
    {
        $form = $this->createForm(SalesReportFilterFormType::class, null, ['user' => $this->getUser()]);
        $form->handleRequest($request);

        $employee = null;
        $months = [];
        $year = (int) date('Y');
        $report = null;

        // isSubmitted() only: report forms carry no CSRF token, so isValid() is always false.
        if ($form->isSubmitted()) {
            $employee = $form->get('employee')->getData();
            $year = (int) ($form->get('year')->getData() ?: date('Y'));
            $months = $this->monthsBetween($form->get('startMonth')->getData(), $form->get('endMonth')->getData());

            if ($employee instanceof User) {
                $report = $this->buildReport($employee, $months, $year);
            }
        }

        return $this->render('@TerminalbdKpi/employeeboard/report/employeeKpiMonthRange.html.twig', [
            'form' => $form->createView(),
            'submitted' => $form->isSubmitted(),
            'employee' => $employee,
            'months' => $months,
            'year' => $year,
            'report' => $report,
        ]);
    }

    /**
     * Month names from start to end inclusive, within one year. A reversed range is swapped.
     */
    private function monthsBetween(?string $start, ?string $end): array
    {
        $current = (int) date('n') - 1;
        $s = array_search($start, self::MONTHS, true);
        $e = array_search($end, self::MONTHS, true);
        $s = $s === false ? $current : $s;
        $e = $e === false ? $current : $e;
        if ($s > $e) {
            [$s, $e] = [$e, $s];
        }

        return array_slice(self::MONTHS, $s, $e - $s + 1);
    }

    private function buildReport(User $employee, array $months, int $year): array
    {
        $em = $this->getDoctrine()->getManager();

        $boards = $em->createQueryBuilder()
            ->select('b.id, b.month, b.process, b.actualMark, b.obtainMark, b.grade, rm.name AS reportModeName')
            ->from(EmployeeBoard::class, 'b')
            ->leftJoin('b.reportMode', 'rm')
            ->where('b.employee = :employee')
            ->andWhere('b.year = :year')
            ->andWhere('b.month IN (:months)')
            ->setParameter('employee', $employee)
            ->setParameter('year', (string) $year)
            ->setParameter('months', $months)
            ->getQuery()->getArrayResult();

        $rows = $em->createQueryBuilder()
            ->select('b.month, at.id AS itemId, at.name AS itemName, ac.name AS activityName')
            ->addSelect('a.actualMark AS maxMark, a.mark, a.targetAmount AS target, a.targetAchievement AS achievement')
            ->from(EmployeeBoardAttribute::class, 'a')
            ->join('a.employeeBoard', 'b')
            ->join('a.attribute', 'at')
            ->leftJoin('a.activity', 'ac')
            ->leftJoin('a.parameter', 'pa')
            ->where('b.employee = :employee')
            ->andWhere('b.year = :year')
            ->andWhere('b.month IN (:months)')
            ->setParameter('employee', $employee)
            ->setParameter('year', (string) $year)
            ->setParameter('months', $months)
            ->orderBy('pa.ordering', 'ASC')
            ->addOrderBy('pa.id', 'ASC')
            ->addOrderBy('ac.ordering', 'ASC')
            ->addOrderBy('ac.id', 'ASC')
            ->addOrderBy('at.ordering', 'ASC')
            ->addOrderBy('at.id', 'ASC')
            ->getQuery()->getArrayResult();

        // Month-level totals, grade and approval state.
        $boardByMonth = [];
        $formats = [];
        $total = ['mark' => 0, 'max' => 0];
        $pending = false;
        foreach ($boards as $board) {
            $boardByMonth[$board['month']] = $board;
            $pending = $pending || $board['process'] !== 'approved';
            if ($board['reportModeName']) {
                $formats[$board['reportModeName']] = true;
            }
            $total['mark'] += (float) $board['obtainMark'];
            $total['max'] += (float) $board['actualMark'];
        }

        // Items in KPI-format order; an item missing from some month (format change) stays blank there.
        $items = [];
        $cells = [];
        foreach ($rows as $row) {
            $itemId = $row['itemId'];
            if (!isset($items[$itemId])) {
                $items[$itemId] = [
                    'name' => $row['itemName'],
                    'activity' => $row['activityName'],
                    'mark' => 0,
                    'max' => 0,
                ];
            }
            $items[$itemId]['mark'] += (float) $row['mark'];
            $items[$itemId]['max'] += (float) $row['maxMark'];
            $cells[$itemId][$row['month']] = $row;
        }

        // Consecutive items sharing an activity, for the grouped header row.
        $activityGroups = [];
        foreach ($items as $item) {
            $last = count($activityGroups) - 1;
            if ($last >= 0 && $activityGroups[$last]['name'] === $item['activity']) {
                $activityGroups[$last]['count']++;
            } else {
                $activityGroups[] = ['name' => $item['activity'], 'count' => 1];
            }
        }

        $formatNames = array_keys($formats);
        if (!$formatNames && $employee->getReportMode()) {
            $formatNames[] = $employee->getReportMode()->getName();
        }

        return [
            'empId' => $employee->getUserId(),
            'name' => $employee->getName(),
            'lineManager' => $employee->getLineManager() ? $employee->getLineManager()->getName() : '',
            'region' => $employee->getRegional() ? $employee->getRegional()->getName() : '',
            'formats' => $formatNames,
            'boards' => $boardByMonth,
            'items' => $items,
            'activityGroups' => $activityGroups,
            'cells' => $cells,
            'total' => $total,
            'pending' => $pending,
        ];
    }
}
