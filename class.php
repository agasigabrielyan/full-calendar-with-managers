<?php if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();
/**
 * Class finds and outputs data into FullCalendar
 *
 * DevConsult, https://dev-consult.ru
 * info@dev-consult.ru
 */
use Bitrix\Crm\Entity\Deal;
use Bitrix\Main\Engine\Action;
use Bitrix\Timeman\Service\DependencyManager;
use Bitrix\Timeman\Form\Schedule\ScheduleForm;
use Bitrix\Timeman\Model\Schedule\ScheduleTable;


class Calendar extends \CBitrixComponent {
    CONST FRANSHIZA_CATEGORY_ID = 1;

    /**
     * method returns DEALS in js script they are events, RESOURCES which are users, HOLIDAYS by users
     *
     * @return array
     */
    public function getData() {
        // 1) получим сделки и activity ВСТРЕЧА связанные
        $deals = \Bitrix\Crm\DealTable::getList([
            'select' => [
                'DEAL_DATA_' => '*',
                'BIND_ACTIVITY_ID' => 'bind.ACTIVITY_ID',
                'ACTIVITY_DATA_' => 'activity.*',
                'USER_ID' => 'activity.RESPONSIBLE_ID',
                'USER_DATA_' => 'users.*',
                'OWNER_TYPE_ID' => 'bind.OWNER_TYPE_ID'
            ],
            'filter' => [
                'CATEGORY_ID' => self::FRANSHIZA_CATEGORY_ID,
                'OWNER_TYPE_ID' => CCrmOwnerType::ResolveID('DEAL'),
                'activity.PROVIDER_TYPE_ID' => 'MEETING'
            ],
            'runtime' => [
                'bind' => [
                    'data_type' => \Bitrix\Crm\ActivityBindingTable::getEntity(),
                    'reference' => [
                        '=this.ID' => 'ref.OWNER_ID'
                    ]
                ],
                'activity' => [
                    'data_type' => \Bitrix\Crm\ActivityTable::getEntity(),
                    'reference' => [
                        '=this.BIND_ACTIVITY_ID' => 'ref.ID'
                    ]
                ],
                'users' => [
                    'data_type' => \Bitrix\Main\UserTable::getEntity(),
                    'reference' => [
                        '=this.USER_ID' => 'ref.ID'
                    ]
                ]
            ]
        ])->fetchAll();

        $resources = [];                // array will consist our managers
        $resourcesIds = [];             // идентификаторы пользователей запишем в отдельный массив
        $events = [];                   // array will consist our events

        foreach($deals as $arDeal) {
            // Создадим массив пользователей и запишем в $resources
            if(!in_array($arDeal['USER_DATA_ID'], $resourcesIds)) {
                $resourcesIds[] = $arDeal['USER_DATA_ID'];
            }
            $resources[] = [
                'id' => $arDeal['USER_DATA_ID'],
                'title' => $arDeal['USER_DATA_NAME'] . " " . $arDeal['USER_DATA_LAST_NAME']
            ];

            $start = $this->fDate($arDeal['ACTIVITY_DATA_START_TIME']);
            $end = $this->fDate($arDeal['ACTIVITY_DATA_END_TIME']);


            $title = $arDeal['ACTIVITY_DATA_SUBJECT'] . " ";

            // Создадим массив сделок и запишм в $events
            $events[] = [
                "id"=>"event" . $arDeal['DEAL_DATA_ID'].$arDeal['DEAL_DATA_ASSIGNED_BY_ID'],
                "resourceId"=>$arDeal['DEAL_DATA_ASSIGNED_BY_ID'],
                "start"=>$start,
                "end"=>$end,
                "title"=> "",
                "rendering" => 'background',
                "url"=>"/crm/deal/details/".$arDeal['DEAL_DATA_ID']."/",
                "className" => "day-class day-class_event",
                "extendedProps"=>[
                    "deal_id" => $arDeal['DEAL_DATA_ID'],
                    "resourceId" => $arDeal['DEAL_DATA_ASSIGNED_BY_ID'],
                    "resourceName" => $arDeal['USER_DATA_NAME'] . " " . $arDeal['USER_DATA_LAST_NAME'],
                    "title" => $title,
                    "url" => "/crm/deal/details/".$arDeal['DEAL_DATA_ID']."/"
                ]
            ];
        }

        // 2) для каждого пользователя из списка $resources необходимо найти дни, когда он отсутствует из инфоблока График отсутствий
        $dbAbsences = \Bitrix\Iblock\Elements\ElementAbsenceTable::getList([
            'select' => [
                "*",
                "USER_PROPERTY_" => "USER",
                "FINISH_STATE_PROPERTY_" => "FINISH_STATE",
                "STATE_PROPERTY_" => "STATE",
                "ABSENCE_TYPE_PROPERTY_" => "ABSENCE_TYPE",
            ],
        ]);

        $absences = [];
        while($abs = $dbAbsences->Fetch()) {
            $absences[] = $abs;
        }
        
        $absencesEvents = [];
        foreach($absences as $singleAbsence) {
            $start = $this->fDate($singleAbsence['ACTIVE_FROM']);
            $end = $this->fDate($singleAbsence['ACTIVE_TO']);

            $title = "Отсутствует: ";
                $title .= $singleAbsence['NAME'] . " ";


            $absencesEvents[] = [
                    "id"=>"absence" . $singleAbsence['ID'].$singleAbsence['USER_PROPERTY_VALUE'],
                    "resourceId"=>$singleAbsence['USER_PROPERTY_VALUE'],
                    "start"=>$start,
                    "end"=>$end,
                    "title"=> $title,
                    "color" => "red",
                    "className" => "day-class absence-day",
                    "extendedProps"=>[
                        "deal_id" => $arDeal['DEAL_DATA_ID'],
                        "resourceId" => $arDeal['DEAL_DATA_ASSIGNED_BY_ID'],
                        "resourceName" => $arDeal['USER_DATA_NAME'] . " " . $arDeal['USER_DATA_LAST_NAME'],
                    ]
                ];
        }

        $events = array_merge($events, $absencesEvents);

        // 3) получим пользователей и их рабочие дни для окрашивания нерабочих дней в красный цвет
        $userWithWorksDays = $this->getUserWithWorksDays();
        $userWithWorksDaysResult = [];
        foreach($userWithWorksDays as $key => $value) {
            if(!empty($value)) {
                // отбросим тех пользователей, которые не значатся в нашем списке $resourcesIds
                foreach($value as $userId => $workDays) {
                    if(in_array($userId, $resourcesIds)) {
                        $userWithWorksDaysResult[$userId] = $workDays;
                    }
                }
            }
        }

        $weekArrayDays = [1,2,3,4,5,6,7];
        $userHolidaysResult = [];
        foreach($userWithWorksDaysResult as $userId => $userSchedule) {
            $holidaysOfUser = array_diff($weekArrayDays, $userSchedule);
            $userHolidaysResult[$userId] = $holidaysOfUser;
        }

        // 4) найдем красные нерабочие дни каждого менеджера, для этого найдем самую первую сделку и самую последнюю
        $startDealsDate = \Bitrix\Crm\DealTable::getList([
            'select' => ['DATE_CREATE'],
            'filter' => ['CATEGORY_ID' => self::FRANSHIZA_CATEGORY_ID],
            'order' => ['DATE_CREATE'],
            'limit' => 1
        ])->fetch();
        $endDealDate = \Bitrix\Crm\DealTable::getList([
            'select' => ['DATE_CREATE'],
            'filter' => ['CATEGORY_ID' => self::FRANSHIZA_CATEGORY_ID],
            'order' => ['DATE_CREATE' => 'DESC'],
            'limit' => 1
        ])->fetch();

        // создадим массив выходных каждого менеджера
        $userHolidaysEventDates = [];
        foreach( $userHolidaysResult as $resourceId => $holidaysOfWeek ) {
            $startDate = new \DateTime( date('Y-m-d', strtotime('-1 year', strtotime( $startDealsDate['DATE_CREATE'] )) ) );
            $endDate   = new \DateTime( date('Y-m-d', strtotime('+1 year', strtotime( $endDealDate['DATE_CREATE'] )) ) );

            $arrayOfHolidays = [];
            $arrayOfHolidays = $this->getArrayOfHolidaysOfPeriod($startDate, $endDate, $holidaysOfWeek);
            $userHolidaysEventDates[$resourceId] = $arrayOfHolidays;
        }


        // 5) обновим массив событий, добавим в него красным цветом выходные без всяких надписей в ней
        $holidaysEvents = [];
        foreach( $userHolidaysEventDates as $managerId => $managerHolidays ) {
            foreach($managerHolidays as $singleHoliday) {
                $holidaysEvents[] = [
                    "id"=>"event" . $managerId.$singleHoliday,
                    "resourceId"=>$managerId,
                    "start"=>$singleHoliday . "T00:00:01",
                    "color" => "red",
                    "end"=>$singleHoliday."T23:59:59",
                    "rendering" => 'background',
                    "title"=> "",
                    "url"=>"",
                    "className" => "day-class day-class_holiday"
                ];
            }
        }

        $events = array_merge($events, $holidaysEvents);

        $data['HOLIDAYS']   =   $userHolidaysResult;
        $data['RESOURCES']  =   $resources;
        $data['EVENTS']     =   $events;
        return $data;
    }

    /**
     * method return array of dates of holidays
     *
     * @param $startDate
     * @param $endDate
     * @param $holidaysOfWeek array массив дней недели выходных пользователя
     * @return array
     */
    function getArrayOfHolidaysOfPeriod( $startDate, $endDate, $holidaysOfWeek ) {
        $arrayOfHolidays = [];

            for( $date = $startDate; $date <= $endDate; $date->modify('+1 day') ){
                $weekDayNumber = intval( date( "w", strtotime($date->format('Y-m-d')) ) );
                $realHolidays = array_values($holidaysOfWeek);

                if($weekDayNumber === 0) {
                    $realDayNumber = 7;
                } else {
                    $realDayNumber = $weekDayNumber;
                }

                if( in_array($realDayNumber, $realHolidays) ) {
                    $arrayOfHolidays[] = $date->format('Y-m-d');
                }
            }

        return $arrayOfHolidays;
    }

    /**
     * method returns users and their worksdays
     *
     * @return array
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    public function getUserWithWorksDays() {

        CModule::IncludeMOdule('timeman');

        $scheduleList = ScheduleTable::getList([
            'select' => ['ID']
        ])->fetchAll();

        $scheduleList = array_column($scheduleList, 'ID');

        $allWorktimeData = [];
        foreach ($scheduleList as $scheduleId) {
            $scheduleRepository = DependencyManager::getInstance()->getScheduleRepository();
            $schedule = $scheduleRepository->findByIdWith($scheduleId, [
                'SHIFTS',  'USER_ASSIGNMENTS'
            ]);

            $provider = DependencyManager::getInstance()->getScheduleProvider();
            $users = $provider->findActiveScheduleUserIds($schedule);
            $scheduleForm = new ScheduleForm($schedule);

            $shiftTemplate = new \Bitrix\Timeman\Form\Schedule\ShiftForm();
            $shiftFormWorkDays = [];
            foreach (array_merge([$shiftTemplate], $scheduleForm->getShiftForms()) as $shiftIndex => $shiftForm)
            {
                $shiftFormWorkDays[] = array_map('intval', str_split($shiftForm->workDays));
            }

            $worktime = [];
            foreach ($users as $userId)
            {
                foreach($shiftFormWorkDays as $key => $value) {
                    if( $value[0] !== 0 ) {
                        $worktime[$userId] = $value;
                    }
                }
            }

            $allWorktimeData[] = $worktime;
        }

        return $allWorktimeData;
    }

    /**
     * method modifies date format to put into FullCalendar format
     *
     * @param $dateObj
     * @return string
     */
    public function fDate($dateObj) {
        $date = date('Y-m-d',($dateObj->getTimestamp()));
        $time = date('H:i:s',($dateObj->getTimestamp()));
        $result = $date . "T" . $time;
        return $result;
    }

    public function executeComponent()
    {
        global $APPLICATION;

        $this->arResult['DATA'] = $this->getData();
        $this->includeComponentTemplate();
        $APPLICATION->SetTitle('Календарь событий');
    }
}