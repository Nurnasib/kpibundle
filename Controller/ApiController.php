<?php
/**
 * Created by PhpStorm.
 * User: shafiq
 * Date: 9/29/19
 * Time: 9:09 PM
 */

namespace Terminalbd\CrmBundle\Controller;

use App\Entity\User;
use App\Entity\Admin\Location;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Encoder\EncoderFactory;
use Symfony\Component\Security\Core\Encoder\MessageDigestPasswordEncoder;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Terminalbd\CrmBundle\Entity\Api;
use Terminalbd\CrmBundle\Entity\ApiDetails;
use Terminalbd\CrmBundle\Entity\BroilerStandard;
use Terminalbd\CrmBundle\Entity\CrmCustomer;
use Terminalbd\CrmBundle\Entity\CrmVisit;
use Terminalbd\CrmBundle\Entity\CrmVisitDetails;
use Terminalbd\CrmBundle\Entity\FcrDetails;
use Terminalbd\CrmBundle\Entity\LayerPerformanceDetails;
use Terminalbd\CrmBundle\Entity\Setting;
use Terminalbd\CrmBundle\Entity\SettingLifeCycle;
use Terminalbd\CrmBundle\Entity\SonaliStandard;
use Terminalbd\CrmBundle\Form\CrmVisitFormType;
use Terminalbd\CrmBundle\Form\FcrDetailsFormType;
use Terminalbd\KpiBundle\Entity\LocationSalesTarget;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class ApiController
 * @package Terminalbd\CrmBundle\Controller
 * @Route("/crm/api")
 */

class ApiController extends AbstractController
{
    /**
     * @Route("/login", methods={"POST","GET"}, options={"expose"=true})
     */
    public function login(Request $request)
    {
        // This data is most likely to be retrieven from the Request object (from Form)
        // But to make it easy to understand ...
        $terminal = 1;

        $formData = $_REQUEST;
        $_username = $formData['username'];
        $_password = $formData['password'];
        $user = $this->getDoctrine()->getRepository(User::class)->checkLoginUser($_username,$_password);
        /// End Retrieve user
        // Check if the user exists !
        if(!$user){
            return new Response(
                'Username doesnt exists',
                Response::HTTP_UNAUTHORIZED,
                array('Content-type' => 'application/json')
            );
        }
        if(!empty($user)){

            /* @var $user User */

            $roles = unserialize(serialize($user->getAppRoles()));
            $rolesSeparated = implode(",", $roles);
            $upozilas =[];
            foreach ($user->getUpozila() as $location):
                $upozilas[]= $location->getId();
            endforeach;
            $locations = implode(",", $upozilas);
            $data = array(
                'userid' => $user->getId(),
                'username' => $user->getUsername(),
                'name' => $user->getName(),
                'roles' => $rolesSeparated,
                'designation' => empty($user->getDesignation()) ? '' : $user->getDesignation()->getName(),
                'lineManager' => empty($user->getLineManager()) ? '' : $user->getLineManager()->getId(),
                'locations' => $locations,
                'upozilas' => $upozilas,
                'status' => 'success'
            );
            $response = new Response();
            $response->headers->set('Content-Type', 'application/json');
            $response->setContent(json_encode($data));
            $response->setStatusCode(Response::HTTP_OK);
            return $response;
        }
    }


    public function checkDuplicateUserAction(Request $request)
    {
        $username = $request->request->get('username');
        $user = $this->getDoctrine()->getManager()->getRepository("UserBundle:User")
            ->checkExistingUser($username);
        if ($user == false) {
            $data = array(
                'status' => 'valid'
            );
        }else {
            $data = array(
                'status' => 'invalid'
            );
        }
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $response->setContent(json_encode($data));
        $response->setStatusCode(Response::HTTP_OK);
        return $response;


    }

    public function editUserAction(Request $request)
    {
        $username = $request->request->get('user_id');
        $user = $this->getDoctrine()->getManager()->getRepository("UserBundle:User")
            ->findOneBy(array('id' => $username));
        $data = array(
            'user_id' => $username,
            'username' => $user->getUsername(),
            'name' => $user->getName(),
            'role' => $user->getAndroidRole()
        );
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $response->setContent(json_encode($data));
        $response->setStatusCode(Response::HTTP_OK);
        return $response;
    }

    public function updateUserAction(Request $request)
    {

        $formData = $request->request->all();
        $username = $formData['user_id'];
        $userExist = $this->getDoctrine()->getRepository('UserBundle:User')->find($username);
        if( $this->checkApiValidation($request) == 'invalid') {

            return new Response('Unauthorized access.', 401);

        }elseif($userExist){

            $this->getDoctrine()->getRepository('UserBundle:User')->androidUserUpdate($userExist,$formData);
            $data = array('status' => 'success');
            $response = new Response();
            $response->headers->set('Content-Type', 'application/json');
            $response->setContent(json_encode($data));
            $response->setStatusCode(Response::HTTP_OK);
            return $response;

        }else{

            return new Response('Unauthorized access.', 401);
        }
    }

    public function forgetPasswordAction(Request $request)
    {

        $username = $request->request->get('username');
        $user = $this->getDoctrine()->getManager()->getRepository("UserBundle:User")
            ->checkLoginUser($username);
        if (empty($user)) {
            return new Response('Unauthorized access.', 401);
        }else{
            $data = array(
                'licenseKey' => $user->getGlobalOption()->getUniqueCode(),
                'username' => $user->getUsername(),
                'name' => $user->getName(),
                'status' => 'success'
            );
            $response = new Response();
            $response->headers->set('Content-Type', 'application/json');
            $response->setContent(json_encode($data));
            $response->setStatusCode(Response::HTTP_OK);
            return $response;
        }

    }

    public function resetPasswordAction(Request $request)
    {

        if( $this->checkApiValidation($request) == 'invalid') {

            return new Response('Unauthorized access.', 401);

        }else{
            $entity = $this->checkApiValidation($request);
            $username = $request->request->get('username');
            $password = $request->request->get('password');
            $user = $this->getDoctrine()->getManager()->getRepository("UserBundle:User")
                ->findOneBy(array('username' => $username,'enabled' => 1));
            if(empty($user)){
                return new Response('Unauthorized access.', 401);
            }

            $user->setPlainPassword($password);
            $this->get('fos_user.user_manager')->updateUser($user);
            $data = array(
                'username' => $user->getUsername(),
                'name' => $user->getProfile()->getName(),
                'status' => 'success'
            );
            $response = new Response();
            $response->headers->set('Content-Type', 'application/json');
            $response->setContent(json_encode($data));
            $response->setStatusCode(Response::HTTP_OK);
            return $response;
        }
    }



    /**
     * @Route("/agent", name="crm_api_agent" , methods={"POST","GET"}, options={"expose"=true})
     */
    public function apiAgent()
    {
        set_time_limit(0);
        ignore_user_abort(true);
        $locations = isset($_REQUEST['locations']) ? $_REQUEST['locations'] : "";
        //$terminal = $this->getUser()->getTerminal()->getId();
        $entities = $this->getDoctrine()->getRepository(Api::class)->apiAgent(1,$locations);
       
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $response->setContent(json_encode($entities));
        $response->setStatusCode(Response::HTTP_OK);
        return $response;
    }

    /**
     * @Route("/customer", name="customerApi")
     */
    public function customerApi()
    {
        set_time_limit(0);
        ignore_user_abort(true);
        //$terminal = $this->getUser()->getTerminal()->getId();
        $locations = isset($_REQUEST['locations']) ? $_REQUEST['locations'] : "";
        $entities = $this->getDoctrine()->getRepository(Api::class)->customerApi(1,$locations);
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $response->setContent(json_encode($entities));
        $response->setStatusCode(Response::HTTP_OK);
        return $response;
    }

    /**
     * @Route("/crmvisit", methods={"POST"}, name="crmvisit")
     */
    public function crmVisit(Request $request)
    {
        set_time_limit(0);
        ignore_user_abort(true);
        $username = isset($_REQUEST['username']) ? $_REQUEST['username'] : "";
        //$terminal = $this->getUser()->getTerminal()->getId();
        $entities = $this->getDoctrine()->getRepository(Api::class)->crmVisit(1,$username);

        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $response->setContent(json_encode($entities));
        $response->setStatusCode(Response::HTTP_OK);
        return $response;
    }

    /**
     * @Route("/employee", methods={"POST"}, name="employeeApi")
     */
    public function employeeApi(Request $request)
    {
        set_time_limit(0);
        ignore_user_abort(true);
        $username = isset($_REQUEST['username']) ? $_REQUEST['username'] : "";
        //$terminal = $this->getUser()->getTerminal()->getId();
        $entities = $this->getDoctrine()->getRepository(Api::class)->employeeApi(1, $username);

        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $response->setContent(json_encode($entities));
        $response->setStatusCode(Response::HTTP_OK);
        return $response;
    }

    /**
     * @Route("/broiler/standard", name="crm_api_brolier")
     */
    public function apiBroiler()
    {
        set_time_limit(0);
        ignore_user_abort(true);
        //$terminal = $this->getUser()->getTerminal()->getId();
        $entities = $this->getDoctrine()->getRepository(Api::class)->apiBroiler(1);

        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $response->setContent(json_encode($entities));
        $response->setStatusCode(Response::HTTP_OK);
        return $response;
    }

    /**
     * @Route("/sonali/standard", name="crm_api_Sonali")
     */
    public function apiSonali()
    {
        set_time_limit(0);
        ignore_user_abort(true);
        //$terminal = $this->getUser()->getTerminal()->getId();
        $entities = $this->getDoctrine()->getRepository(Api::class)->apiSonali(1);

        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $response->setContent(json_encode($entities));
        $response->setStatusCode(Response::HTTP_OK);
        return $response;
    }

    /**
     * @Route("/setting/life-cycle", name="crm_api_apiLifeCycleSetting")
     */
    public function apiLifeCycleSetting()
    {
        set_time_limit(0);
        ignore_user_abort(true);
        //$terminal = $this->getUser()->getTerminal()->getId();
        $entities = $this->getDoctrine()->getRepository(Api::class)->apiLifeCycleSetting(1);

        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $response->setContent(json_encode($entities));
        $response->setStatusCode(Response::HTTP_OK);
        return $response;
    }

    /**
     * @Route("/setting", name="apiSetting")
     */
    public function apiSetting()
    {
        set_time_limit(0);
        ignore_user_abort(true);
        //$terminal = $this->getUser()->getTerminal()->getId();
        $entities = $this->getDoctrine()->getRepository(Api::class)->apiSetting(1);

        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $response->setContent(json_encode($entities));
        $response->setStatusCode(Response::HTTP_OK);
        return $response;
    }

    /**
     * @Route("/layer/standard", name="apiLayer")
     */
    public function apiLayer()
    {
        set_time_limit(0);
        ignore_user_abort(true);
        //$terminal = $this->getUser()->getTerminal()->getId();
        $entities = $this->getDoctrine()->getRepository(Api::class)->apiLayer(1);

        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $response->setContent(json_encode($entities));
        $response->setStatusCode(Response::HTTP_OK);
        return $response;
    }


    /**
     * @Route("/report/farmer-introduce-report", methods={"POST"}, name="farmerintroducereport")
     */
    public function farmerIntroduceReport(Request $request)
    {
        $breedName = $request->request->get('breed_name');
        $employeeName = $request->request->get('employee');

        $employee = $this->getDoctrine()->getRepository(User::class)->findOneBy(array('name' => $employeeName));
        //$agentName = $request->request->get('agent_name');

        set_time_limit(0);
        ignore_user_abort(true);
        // $terminal = $this->getUser()->getTerminal()->getId();
        $entities = $this->getDoctrine()->getRepository(Api::class)->farmerIntroduceReport(1,$breedName,$employee);

        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $response->setContent(json_encode($entities));
        $response->setStatusCode(Response::HTTP_OK);
        return $response;
    }
    /**
     * @Route("/report/farmer-touch-report", methods={"POST"}, name="farmertouchreport")
     */
    public function farmerTouchReport(Request $request)
    {
        $startDate = $request->request->get('startDate');
        $endDate = $request->request->get('endDate');
        $reportSlug = $request->request->get('report');
        $employeeName = $request->request->get('employee');

        $report = $this->getDoctrine()->getRepository(Setting::class)->findOneBy(array('slug' => $reportSlug));

        $employee = $this->getDoctrine()->getRepository(User::class)->findOneBy(array('name' => $employeeName));

        set_time_limit(0);
        ignore_user_abort(true);
        $entities = $this->getDoctrine()->getRepository(Api::class)->farmerTouchReport(1,$startDate, $endDate, $report, $employee);

        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $response->setContent(json_encode($entities));
        $response->setStatusCode(Response::HTTP_OK);
        return $response;
    }

    /**
     * @Route("/report/farmer-training-report", methods={"POST"}, name="farmer-training-report")
     */
    public function farmerTrainingReport(Request $request)
    {
        $breedName = $request->request->get('breed_name');
        $employeeName = $request->request->get('employee');

        $employee = $this->getDoctrine()->getRepository(User::class)->findOneBy(array('name' => $employeeName));
        //$agentName = $request->request->get('agent_name');
        set_time_limit(0);
        ignore_user_abort(true);
        // $terminal = $this->getUser()->getTerminal()->getId();
        $entities = $this->getDoctrine()->getRepository(Api::class)->farmerTrainingReport(1,$breedName, $employee);

        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $response->setContent(json_encode($entities));
        $response->setStatusCode(Response::HTTP_OK);
        return $response;
    }



    /**
     * @Route("/report/poultry", methods={"POST"}, name="reportPoultry")
     */
    public function poultryLifeCylceReport(Request $request)
    {
        set_time_limit(0);
        ignore_user_abort(true);
        $startDate = $request->request->get('startDate');
        $endDate = $request->request->get('endDate');
        $reportSlug = $request->request->get('report');
        $customerName = $request->request->get('customer');

        $report = $this->getDoctrine()->getRepository(Setting::class)->findOneBy(array('slug' => $reportSlug));

        $customer = $this->getDoctrine()->getRepository(CrmCustomer::class)->findOneBy(array('name' => $customerName));


        //$terminal = $this->getUser()->getTerminal()->getId();
        $entities = $this->getDoctrine()->getRepository(Api::class)->poultryLifeCylceReport(1, $startDate, $endDate,$reportSlug, $customerName);
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $response->setContent(json_encode($entities));
        $response->setStatusCode(Response::HTTP_OK);
        return $response;
    }

    /**
     * @Route("/cattle/crm-visit", methods={"POST"}, name="cattle-crm-visit")
     */
    public function farmCattleVisit(Request $request)
    {
        $startDate = $request->request->get('startDate');
        $endDate = $request->request->get('endDate');
        $employeeName = $request->request->get('employee');

        set_time_limit(0);
        ignore_user_abort(true);

        $employee = $this->getDoctrine()->getRepository(User::class)->findOneBy(array('name' => $employeeName));

        $entities = $this->getDoctrine()->getRepository(Api::class)->farmVisitCattle(1,$startDate,$endDate,$employee);

        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $response->setContent(json_encode($entities));
        $response->setStatusCode(Response::HTTP_OK);
        return $response;
    }

    /**
     * @Route("/chick/fcr", methods={"POST"}, name="chickfcr")
     */
    public function frcReportPoulty(Request $request)
    {
        set_time_limit(0);
        ignore_user_abort(true);

        $startDate = $request->request->get('startDate');
        $endDate = $request->request->get('endDate');
        $reportSlug = $request->request->get('report');
        $employeeName = $request->request->get('employee');

        $report = $this->getDoctrine()->getRepository(Setting::class)->findOneBy(array('slug' => $reportSlug));

        $employee = $this->getDoctrine()->getRepository(User::class)->findOneBy(array('name' => $employeeName));

        $entities = $this->getDoctrine()->getRepository(Api::class)->frcReportPoulty(1, $startDate, $endDate, $reportSlug,$employeeName);

        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $response->setContent(json_encode($entities));
        $response->setStatusCode(Response::HTTP_OK);
        return $response;
    }

    /**
     * @Route("/fcr/before/{type}/new", methods={"GET", "POST"}, name="api_fcr_before_new", options={"expose"=true})
     */
    // type = sonali or boiler
    public function apiFcrBeforeNew(Request $request, $type): Response
    {
        $data = $request->request->all();
        $slug = 'fcr-before-sale-'.$type;
        $report = $this->getDoctrine()->getRepository(Setting::class)->findOneBy(['slug'=>$slug, 'settingType'=>'FARMER_REPORT', 'status'=>1]);

        $hatchery = $this->getDoctrine()->getRepository(Setting::class)->findOneBy(['id'=>$data['hatchery_id'], 'status'=>1]);

        $breed = $this->getDoctrine()->getRepository(Setting::class)->findOneBy(['id'=>$data['breed_id'], 'status'=>1]);

        $feed = $this->getDoctrine()->getRepository(Setting::class)->findOneBy(['id'=>$data['feed_id'], 'status'=>1]);

        $feedMill = $this->getDoctrine()->getRepository(Setting::class)->findOneBy(['id'=>$data['feed_Mill'], 'status'=>1]);

        $feedType = $this->getDoctrine()->getRepository(Setting::class)->findOneBy(['id'=>$data['feedType'], 'status'=>1]);

        $employee = $this->getDoctrine()->getRepository(User::class)->find($data['user_id']);

        $customer = $this->getDoctrine()->getRepository(CrmCustomer::class)->find($data['customer_id']);

        $entity = new FcrDetails();
        $reportingDate = date('Y-m-d',strtotime('now'));
        $hatchingDate = date('Y-m-d',strtotime('now'));
        $proDate = date('Y-m-d',strtotime('now'));
        $entity->setReportingMonth(new \DateTime($reportingDate));
        $entity->setHatchingDate(new \DateTime($hatchingDate));
        $entity->setProDate(new \DateTime($proDate));
        $entity->setFcrOfFeed(strtoupper('before'));
        $entity->setCustomer($customer);
        $entity->setReport($report);
        $entity->setAgent($customer->getAgent());
        $entity->setEmployee($employee);
        $entity->setTotalBirds($data['totalBirds']);
        $entity->setAgeDay($data['age']);
        $entity->setMortalityPes($data['mortality_pcs']);
        $entity->setHatchery($hatchery?$hatchery:null);
        $entity->setBreed($breed?$breed:null);
        $entity->setFeed($feed?$feed:null);
        $entity->setFeedMill($feedMill?$feedMill:null);
        $entity->setFeedType($feedType?$feedType:null);
        $entity->setBatchNo($data['batch_no']);
        $entity->setRemarks($data['remarks']);

        if($report->getSlug()=='fcr-before-sale-sonali'){

            /* @var SonaliStandard $sonaliStandard*/
            $sonaliStandard= $this->getDoctrine()->getRepository(SonaliStandard::class)->findOneBy(array('age'=>$entity->getAgeDay()));
            if($sonaliStandard){
                $entity->setWeightStandard($sonaliStandard->getTargetBodyWeight());
                $entity->setFeedConsumptionStandard($sonaliStandard->getCumulativeFeedIntake());
            }
        }
        if($report->getSlug()=='fcr-before-sale-boiler'){

            /* @var BroilerStandard $broilerStandard*/
            $broilerStandard= $this->getDoctrine()->getRepository(BroilerStandard::class)->findOneBy(array('age'=>$entity->getAgeDay()));
            if($broilerStandard){
                $entity->setWeightStandard($broilerStandard->getTargetBodyWeight());
                $entity->setFeedConsumptionStandard($broilerStandard->getTargetFeedConsumption());
            }
        }
        $entity->setMortalityPercent($entity->calculateMortalityPercent());
        $entity->setFeedConsumptionPerBird($entity->calculatePerBird());
        $entity->setFcrWithoutMortality($entity->calculateWithoutMortality());
        $entity->setFcrWithMortality($entity->calculateWithMortality());


        $em = $this->getDoctrine()->getManager();
        $em->persist($entity);
        $em->flush();

        return new JsonResponse('success');
    }

    /**
     * @Route("/crmvisitingarea", methods={"GET","POST"}, name="crmVisitingArea")
     */
    public function crmVisitingArea(Request $request)
    {

        $terminal = 1;

        $formData = $_REQUEST;
        $userId = $request->request->get('user_id');

        $user = $this->getDoctrine()->getRepository(User::class)->find($userId);

        if (!empty($user)) {

            /* @var $user User */

            $upozilas = [];
            foreach ($user->getUpozila() as $location):
                $upozilas[] = $location->getId();
                $upozilaName[] = $location->getName();
            endforeach;
            $locations = implode(",", $upozilas);
            $data = array(
                'upozilas' => $upozilas,
                'upozilaName' => $upozilaName
            );
            $response = new Response();
            $response->headers->set('Content-Type', 'application/json');
            $response->setContent(json_encode($data));
            $response->setStatusCode(Response::HTTP_OK);
            return $response;
        }
    }

    /**
     * @Route("/crmvisitnew", methods={"GET","POST"}, name="crmVisitNew")
     */
    public function new(Request $request)
    {
        $data = $request->request->all();

        $employee = $this->getDoctrine()->getRepository(User::class)->find($data['user_id']);

        $locations = $this->getDoctrine()->getRepository(Location::class)->find($data['id']);

        $entity = new CrmVisit();
        $entity->setEmployee($employee);
        $entity->setLocation($locations);
        $entity->setWorkingDuration($data['workingduration']);
        $entity->setWorkingDurationTo($data['workingdurationto']);
        $em = $this->getDoctrine()->getManager();
        $em->persist($entity);
        $em->flush();

        return new JsonResponse('success');
    }

    /**
     * @Route("/dailyActiviesPurpose", methods={"GET"}, name="dailyActiviesPurpose")
     */
    public function dailyActiviesPurpose()
    {

        set_time_limit(0);
        ignore_user_abort(true);

        $entities = $this->getDoctrine()->getRepository(Api::class)->dailyActiviesPurpose(1);
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $response->setContent(json_encode($entities));
        $response->setStatusCode(Response::HTTP_OK);
        return $response;
    }

    /**
     * @Route("/vehicle", methods={"GET"}, name="vehicle")
     */
    public function vehicle()
    {

        set_time_limit(0);
        ignore_user_abort(true);

        $entities = $this->getDoctrine()->getRepository(Api::class)->vehicle(1);
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $response->setContent(json_encode($entities));
        $response->setStatusCode(Response::HTTP_OK);
        return $response;
    }

    /**
     * @Route("/farmerSelectPurpose", methods={"GET","POST"}, name="farmerSelectPurpose")
     */
    public function farmerSelectPurpose(Request $request)
    {
        set_time_limit(0);
        ignore_user_abort(true);

        $employeeId = $request->request->get('user_id');

        $employee = $this->getDoctrine()->getRepository(User::class)->findOneBy(array('id' => $employeeId));

        $entities = $this->getDoctrine()->getRepository(Api::class)->farmerSelectPurpose(1, $employee);

        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $response->setContent(json_encode($entities));
        $response->setStatusCode(Response::HTTP_OK);
        return $response;
    }

    /**
     * @Route("/selectFarmType", methods={"GET"}, name="selectFarmType")
     */
    public function selectFarmType()
    {

        set_time_limit(0);
        ignore_user_abort(true);

        $entities = $this->getDoctrine()->getRepository(Api::class)->selectFarmType(1);
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $response->setContent(json_encode($entities));
        $response->setStatusCode(Response::HTTP_OK);
        return $response;
    }



    /**
     * @Route("/farmSelectReport", methods={"GET"}, name="farmSelectReport")
     */
    public function farmSelectReport()
    {
        set_time_limit(0);
        ignore_user_abort(true);

        $entities = $this->getDoctrine()->getRepository(Api::class)->farmSelectReport(1);

        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $response->setContent(json_encode($entities));
        $response->setStatusCode(Response::HTTP_OK);
        return $response;
    }

    /**
     * @Route("/searchfarmer", methods={"POST"}, name="searchfarmer")
     */
    public function searchfarmer(Request $request)
    {
        set_time_limit(0);
        ignore_user_abort(true);

        $data = $request->request->all();

        $employee = $this->getDoctrine()->getRepository(User::class)->find($data['user_id']);

        $entities = $this->getDoctrine()->getRepository(Api::class)->searchfarmer($employee);

        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $response->setContent(json_encode($entities));
        $response->setStatusCode(Response::HTTP_OK);
        return $response;

    }

    /**
     * @Route("/newfarmertype", methods={"GET"}, name="newfarmertype")
     */
    public function newFarmType()
    {

        set_time_limit(0);
        ignore_user_abort(true);

        $entities = $this->getDoctrine()->getRepository(Api::class)->newfarmertype(1);
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $response->setContent(json_encode($entities));
        $response->setStatusCode(Response::HTTP_OK);
        return $response;
    }

    /**
     * @Route("/ageweek", methods={"GET"}, name="ageweek")
     */
    public function ageweek()
    {

        set_time_limit(0);
        ignore_user_abort(true);

        $entities = $this->getDoctrine()->getRepository(Api::class)->ageweek(1);
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $response->setContent(json_encode($entities));
        $response->setStatusCode(Response::HTTP_OK);
        return $response;
    }

    /**
     * @Route("/feedType", methods={"GET"}, name="feedType")
     */
    public function feedType()
    {

        set_time_limit(0);
        ignore_user_abort(true);

        $entities = $this->getDoctrine()->getRepository(Api::class)->feedType(1);
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $response->setContent(json_encode($entities));
        $response->setStatusCode(Response::HTTP_OK);
        return $response;
    }



    /**
     * @Route("/hatchery", methods={"GET"}, name="hatchery")
     */
    public function hatchery()
    {

        set_time_limit(0);
        ignore_user_abort(true);

        $entities = $this->getDoctrine()->getRepository(Api::class)->hatchery(1);
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $response->setContent(json_encode($entities));
        $response->setStatusCode(Response::HTTP_OK);
        return $response;
    }

    /**
     * @Route("/feedMill", methods={"GET"}, name="feedMill")
     */
    public function feedMill()
    {

        set_time_limit(0);
        ignore_user_abort(true);

        $entities = $this->getDoctrine()->getRepository(Api::class)->feedMill(1);
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $response->setContent(json_encode($entities));
        $response->setStatusCode(Response::HTTP_OK);
        return $response;
    }

    /**
     * @Route("/breedType", methods={"GET"}, name="breedType")
     */
    public function breedType()
    {

        set_time_limit(0);
        ignore_user_abort(true);

        $entities = $this->getDoctrine()->getRepository(Api::class)->breedType(1);
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $response->setContent(json_encode($entities));
        $response->setStatusCode(Response::HTTP_OK);
        return $response;
    }

    /**
     * @Route("/color", methods={"GET"}, name="color")
     */
    public function color()
    {

        set_time_limit(0);
        ignore_user_abort(true);

        $entities = $this->getDoctrine()->getRepository(Api::class)->color(1);
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $response->setContent(json_encode($entities));
        $response->setStatusCode(Response::HTTP_OK);
        return $response;
    }

    /**
     * @Route("/feed", methods={"GET"}, name="feed")
     */
    public function feed()
    {

        set_time_limit(0);
        ignore_user_abort(true);

        $entities = $this->getDoctrine()->getRepository(Api::class)->feed(1);
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $response->setContent(json_encode($entities));
        $response->setStatusCode(Response::HTTP_OK);
        return $response;
    }

    /**
     * @Route("/disease", methods={"GET"}, name="disease")
     */
    public function disease()
    {

        set_time_limit(0);
        ignore_user_abort(true);

        $entities = $this->getDoctrine()->getRepository(Api::class)->disease(1);
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $response->setContent(json_encode($entities));
        $response->setStatusCode(Response::HTTP_OK);
        return $response;
    }

    /**
     * @Route("/product", methods={"GET"}, name="product")
     */
    public function product()
    {

        set_time_limit(0);
        ignore_user_abort(true);

        $entities = $this->getDoctrine()->getRepository(Api::class)->product(1);
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $response->setContent(json_encode($entities));
        $response->setStatusCode(Response::HTTP_OK);
        return $response;
    }

    /**
     * @Route("/mainculturespecies", methods={"GET"}, name="mainculturespecies")
     */
    public function mainculturespecies()
    {

        set_time_limit(0);
        ignore_user_abort(true);

        $entities = $this->getDoctrine()->getRepository(Api::class)->mainculturespecies(1);
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $response->setContent(json_encode($entities));
        $response->setStatusCode(Response::HTTP_OK);
        return $response;
    }

    /**
     * @Route("/usercrmvisitingarea", methods={"POST"}, name="usercrmvisitingarea")
     */
    public function usercrmvisitingarea(Request $request)
    {

        set_time_limit(0);
        ignore_user_abort(true);

        $employeeId = $request->request->get('user_id');

        $entities = $this->getDoctrine()->getRepository(Api::class)->usercrmvisitingarea($employeeId);
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $response->setContent(json_encode($entities));
        $response->setStatusCode(Response::HTTP_OK);
        return $response;
    }

    /**
     * @Route("/dairyBreedType", methods={"GET"}, name="dairyBreedType")
     */
    public function dairyBreedType()
    {

        set_time_limit(0);
        ignore_user_abort(true);

        $entities = $this->getDoctrine()->getRepository(Api::class)->dairyBreedType(1);
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $response->setContent(json_encode($entities));
        $response->setStatusCode(Response::HTTP_OK);
        return $response;
    }

    /**
     * @Route("/fatteningBreedType", methods={"GET"}, name="fatteningBreedType")
     */
    public function fatteningBreedType()
    {

        set_time_limit(0);
        ignore_user_abort(true);

        $entities = $this->getDoctrine()->getRepository(Api::class)->fatteningBreedType(1);
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $response->setContent(json_encode($entities));
        $response->setStatusCode(Response::HTTP_OK);
        return $response;
    }


    /**
     * @Route("/store-json-data", methods={"POST"}, name="store_json_data")
     * @param Request $request
     * @return JsonResponse
     */
    public function storeAllJsonDataFromApi(Request $request)
    {
        set_time_limit(0);
        ignore_user_abort(true);

        $data = $request->request->all();

        if ($data){
            $em = $this->getDoctrine()->getManager();

            $findParent = $this->getDoctrine()->getRepository(Api::class)->findOneBy(['deviceId' => $data['device_id'], 'employeeId' => $data['employee_id']]);
            if (!$findParent){
                $api = new Api();
                $api->setDeviceId($data['device_id'] ?: null);
                $api->setEmployeeId($data['employee_id'] ?: null);
                $api->setStatus(0);
                $api->setCreatedAt(new \DateTime('now'));

                $apiDetails = new ApiDetails();
                $apiDetails->setBatch($api);
                $apiDetails->setProcess($data['process']);
                $apiDetails->setJsonData($data['json_body']);
                $apiDetails->setStatus(0);
                $api->addApiDetails($apiDetails);
                $em->persist($api);
                $em->flush();

            } else {
                $apiDetails = new ApiDetails();
                $apiDetails->setBatch($findParent);
                $apiDetails->setProcess($data['process']);
                $apiDetails->setJsonData($data['json_body']);
                $apiDetails->setStatus(0);
                $em->persist($apiDetails);
                $em->flush();
            }
            return new JsonResponse([
                'status' => 200,
            ]);
        } else {
            return new JsonResponse([
                'status' => false,
            ]);
        }
    }

    /**
     * @Route("/list", name="api_response_list")
     */
    public function apiResponseList()
    {
/*        $array = [
            'crm_visit' => [
                'id' => 1,
                'duration_to' => null,
                'duration_from' => '1:47 PM',
                'employee_id' => 23,
                'location_id' => 340,
                'visitAreaName' => 'SARISHABARI UPAZILA',
                'created_at' => '05-05-2021',
            ],
            'farmer_report' => [
                [
                  "id" => 4,
                  "crm_visit_id" => 1,
                  "farmCapacity" => "5",
                  "updated" => null,
                  "comments" => "gvb",
                  "created" => "05-05-2021",
                  "customer_id" => 23,
                  "process" => null,
                  "agent_id" => 1742,
                  "purpose_id" => 16,
                  "firm_type_id" => 223,
                  "report_id" => 239,
                  "purposeName" => "Market Promotion",
                  "farmerName" => "Md Rakibul Hasan",
                  "phoneNumber" => "7000804",
                  "farmTypeName" => "Others (Poultry)",
                  "reportTypeName" => "Antibiotic Free Farm Poultry"
                ],
                [
                    "id" => 3,
                    "crm_visit_id" => 1,
                    "farmCapacity" => "5",
                    "updated" => null,
                    "comments" => "g",
                    "created" => "05-05-2021",
                    "customer_id" => 23,
                    "process" => null,
                    "agent_id" => 1742,
                    "purpose_id" => 13,
                    "firm_type_id" => 224,
                    "report_id" => 219,
                    "purposeName" => "Problem Farm Visit",
                    "farmerName" => "Tanveer",
                    "phoneNumber" => "4512",
                    "farmTypeName" => "Others (Fish)",
                    "reportTypeName" => "Farmer Touch Report"
                ]
            ],
        ];
        echo json_encode($array);
        die();*/

        $list = $this->getDoctrine()->getRepository(Api::class)->getData();
        return $this->render('@TerminalbdCrm/api/api-response-list.html.twig',[
            'list' => $list,
        ]);
    }

    /**
     * @Route("/{id}/insert-data", name="insert_json_data")
     * @param Api $api
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function insertDataIntoCorrespondingTable(Api $api)
    {
        set_time_limit(0);
        ignore_user_abort(true);

        if ($api->isStatus() == 0){
            $jsonToArray = json_decode($api->getJsonData(), true);
            if ($api->getProcess() == 'crm_visit'){
                foreach( $jsonToArray as $data){
                    $this->getDoctrine()->getRepository(CrmVisit::class)->insertDataFromApi($data);
                }
            }elseif ($api->getProcess() == 'farmer_report'){
                foreach( $jsonToArray as $data){
                    if ($data['crm_visit_id'] !== null){
                        $findVisit = $this->getDoctrine()->getRepository(CrmVisit::class)->findOneBy(['appId' => $data['crm_visit_id']]);
                        if ($findVisit){
                            $this->getDoctrine()->getRepository(CrmVisitDetails::class)->insertDataFromApi($data, $findVisit->getId());
                        }
                    }
                }
            }elseif ($api->getProcess() == 'layer_performance_report'){
                foreach( $jsonToArray as $data){
                    $this->getDoctrine()->getRepository(LayerPerformanceDetails::class)->insertDataFromApi($data);
                }
            }
            $api->setStatus(1);
            $em = $this->getDoctrine()->getManager();
            $em->persist($api);
            $em->flush();
            $this->addFlash('success', 'Data has been migrated!');
            return $this->redirectToRoute('api_response_list');
        }else{
            $this->addFlash('error', 'Somthing Wrong!');
            return $this->redirectToRoute('api_response_list');
        }
//        $entities = $this->getDoctrine()->getRepository(Api::class)->getJsonData();
//        dd($jsonData);
//        foreach ($entities as $data){
//            if($data['porcess'] == 'CUSTOMER_VISIT'){
//
//                $jsonToArray = json_decode($data['jsonData'], true);
//                dd($jsonToArray);
//            }
//        }

    }

}