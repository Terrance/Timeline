$(document).ready(function() {
    function formatDate(date, pattern) {
        if (!date) {
            date = new Date();
        }
        if (!pattern) {
            pattern = "dd/mm/yyyy HH:MM:SS";
        }
        function pad(n) {
            return n < 10 ? "0" + n : n;
        }
        return pattern.split("yyyy").join(date.getFullYear())
            .split("yy").join(date.getFullYear().toString().substring(2))
            .split("mm").join(pad(date.getMonth() + 1))
            .split("m").join(date.getMonth() + 1)
            .split("dd").join(pad(date.getDate()))
            .split("d").join(date.getDate())
            .split("HH").join(pad(date.getHours()))
            .split("H").join(date.getHours())
            .split("MM").join(pad(date.getMinutes()))
            .split("M").join(date.getMinutes())
            .split("SS").join(pad(date.getSeconds()))
            .split("S").join(date.getSeconds());
    }
    $.ajax({
        url: "data/github.json",
        success: function(resp, stat, xhr) {
            $(".container").empty();
            $.each(resp, function(i, item) {
                var panel = $("<div/>").addClass("panel panel-default")
                    .append($("<div/>").addClass("panel-body").text(item.desc));
                if (item.links.length) {
                    var footer = $("<div/>").addClass("panel-footer");
                    $.each(item.links, function(j, link) {
                        footer.append($("<a/>").addClass("btn btn-default btn-sm")
                                          .attr("href", link.link)
                                          .append($("<i/>").addClass("fa fa-" + link.icon))
                                          .append(link.text));
                    });
                    footer.append($("<a/>").addClass("btn btn-default btn-sm pull-right")
                                      .append($("<i/>").addClass("fa fa-clock-o"))
                                      .append(formatDate(new Date(item.time))));
                    panel.append(footer);
                }
                $(".container").append(panel);
            });
            $("a").click(function(e) {
                if (this.href) {
                    window.open(this.href);
                    e.preventDefault();
                }
            });
        }
    });
});
