BX.ready(function() {
    $(function() {
        $('#calendar').fullCalendar({
          schedulerLicenseKey: 'GPL-My-Project-Is-Open-Source',
          timeFormat: 'h:mm',
          locale: 'ru',
          displayEventTime : false,
          monthNames: ['Январь','Февраль','Март','Апрель','Май','Июнь','Июль','Август','Сентябрь','Октябрь','Ноябрь','Декабрь'],
            monthNamesShort: ['Янв.','Фев.','Март','Апр.','Май','Июнь','Июль','Авг.','Сент.','Окт.','Ноя.','Дек.'],
            dayNames: ["Воскресенье","Понедельник","Вторник","Среда","Четверг","Пятница","Суббота"],
            dayNamesShort: ["Воскресенье","Понедельник","Вторник","Среда","Четверг","Пятница","Суббота"],
            buttonText: {
                prev: "<",
                next: ">",
                prevYear: "<<",
                nextYear: ">>",
                today: "Сегодня",
                month: "Месяц",
                week: "Неделя",
                day: "День"
           },
          editable: false, // enable draggable events
          droppable: false, // this allows things to be dropped onto the calendar
          aspectRatio: 2,
          scrollTime: '00:00', // undo default 6am scrollTime
          slotWidth: 100,
          header: {
            left: 'today prev,next',
            center: 'title',
            right: 'timelineDay,timelineWeek,timelineMonth'
          },
          defaultView: 'timelineMonth',
          views: {
            timelineWeek: {
              type: 'timeline',
              duration: { days: 7 }
            },
            timelineMonth: {
                type: 'timeline',
                duration: { days: 30 }
            }
          },
          resourceLabelText: 'Менеджеры',
          resources: BX.message('RESOURCES'),
          events: BX.message('EVENTS'),
          drop: function(date, jsEvent, ui, resourceId) {
              /*debugger;*/
          },
          eventReceive: function(event) {
               /*debugger;*/
          },
          eventDrop: function(event) {

          },
          dayClick: function(date, jsEvent, view, resourceObj) {
                debugger;
          },
          dayRender: function(day, cell) {
                /*cell.css({backgroundColor: '#68a945'});*/
          },
          eventRender: function (event, element, monthView) {

              let additionalEventClassName = "";
              if(monthView.type === "timelineDay") {
                    additionalEventClassName = "custom-event-of-day";
              } else if (monthView.type === "timelineWeek") {
                    additionalEventClassName = "custom-event-of-week";
              }
              element.addClass(additionalEventClassName);

            if(event.extendedProps) {
                let classOfEvent = event.className;
                let newDescription = "<a href='" + event.extendedProps.url + "' class='event-cell'>";
                        newDescription += "<span>№ '" + event.extendedProps.deal_id + "'</span><br/>"
                        newDescription += "'" + event.extendedProps.title + "'<br/>";
                    newDescription += "</a>"
                element.append(newDescription);
            }
          },
          viewRender: function(info) {


              let fcCellTextTime = $(".fc-cell-text-time");
              let timeCoordination = [];
                  timeCoordination['12am'] = '00';
                  timeCoordination['1am']  = '01';
                  timeCoordination['2am']  = '02';
                  timeCoordination['3am']  = '03';
                  timeCoordination['4am']  = '04';
                  timeCoordination['5am']  = '05';
                  timeCoordination['6am']  = '06';
                  timeCoordination['7am']  = '07';
                  timeCoordination['8am']  = '08';
                  timeCoordination['9am']  = '09';
                  timeCoordination['10am'] = '10';
                  timeCoordination['11am'] = '11';

                  timeCoordination['12pm'] = '12';
                  timeCoordination['1pm']  = '13';
                  timeCoordination['2pm']  = '14';
                  timeCoordination['3pm']  = '15';
                  timeCoordination['4pm']  = '16';
                  timeCoordination['5pm']  = '17';
                  timeCoordination['6pm']  = '18';
                  timeCoordination['7pm']  = '19';
                  timeCoordination['8pm']  = '20';
                  timeCoordination['9pm']  = '21';
                  timeCoordination['10pm'] = '22';
                  timeCoordination['11pm'] = '23';

              for(let i=0; i<fcCellTextTime.length; i++) {
                let currentText = $(fcCellTextTime[i]).text();
                $(fcCellTextTime[i]).text(timeCoordination[currentText]);
              }

              // Задать выходные дни
              let timelineType = info.name;

          },
        });
     });
});

