define(['jquery'], function () {
    return {
        init: function () {
            $(document).ready(function () {
                $('.download').click(function () {
                    top.window.onbeforeunload = null;
                    var forum = $('#id_forum').val();
                    var group = $('#id_group').val();
                    var grouping = $('#id_grouping').val();
                    var starttime = $('#id_starttime').val();
                    var endtime = $('#id_endtime').val();
                    var courseid = $('#my_courseid').val();
                    var country = $('#id_country').val();
                    window.location.replace('download.php?forum=' + forum + '&group=' + group + '&starttime=' + starttime +
                        '&endtime=' + endtime + '&course=' + courseid + '&grouping=' + grouping + '&country=' + country);

                });
            })
        }
    }
})