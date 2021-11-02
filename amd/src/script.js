define(['jquery'], function () {
    return {
        init: function () {
            $(document).ready(function () {
                $('.download').click(function () {
                    top.window.onbeforeunload = null;
                    var forum = $('#id_forum').val();
                    var group = $('#id_group').val();
                    var grouping = $('#id_grouping').val();
                    start = $('#id_starttime_enabled').is(":checked")
                    end = $('#id_endtime_enabled').is(":checked");
                    if (start == true) {
                        var starttime_day = $('#id_starttime_day').val();
                        var starttime_month = $('#id_starttime_month').val();
                        var starttime_year = $('#id_starttime_year').val();
                        var starttime_hour = $('#id_starttime_hour').val();
                        var starttime_minute = $('#id_starttime_minute').val();
                        var starttime = new Date(starttime_month + "-" + starttime_day + "-" + starttime_year + " " + starttime_hour + ":" + starttime_minute).getTime();
                        starttime = starttime / 1000;
                    } else {
                        var starttime = "";

                    }
                    if (end == true) {
                        var endtime_day = $('#id_endtime_day').val();
                        var endtime_month = $('#id_endtime_month').val();
                        var endtime_year = $('#id_endtime_year').val();
                        var endtime_hour = $('#id_endtime_hour').val();
                        var endtime_minute = $('#id_endtime_minute').val();
                        var endtime = new Date(endtime_month + "-" + endtime_day + "-"
                            + endtime_year + " " + endtime_hour + ":" + endtime_minute).getTime();
                        endtime = endtime / 1000;
                    } else {
                        var endtime = "";

                    }
                    var courseid = $('#my_courseid').val();
                    var country = $('#id_country').val();
                    window.location.replace('download.php?forum=' + forum + '&group=' + group + '&starttime=' + starttime +
                        '&endtime=' + endtime + '&course=' + courseid + '&grouping=' + grouping + '&country=' + country);

                });
            })
        }
    }
})