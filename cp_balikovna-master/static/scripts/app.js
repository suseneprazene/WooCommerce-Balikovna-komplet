$(document).ready(function () {

    var $body = $("body");

    function selectTemplate(data, container) {
        var branchId = data.id;
        var city = data.city;
        var cityPart = data.cityPart;
        var address = data.address;
        var kind = data.kind;
        if (kind === "posta") {
            kind = "pošta"
        }

        if (!branchId) {
            return "";
        } else {
            return $(
                "<div class='city'>" + city + "</div>" +
                "<div class='cityPart'>" + cityPart + "</div>" +
                "<div class='address'>" + address + "</div>" +
                "<div class='kind'>" + kind + "</div>" +
                "<div class='openingHours tooltipTrigger' data-url='/api/hours/"+ branchId +"/'>" + "" + "</div>"
            );
        }
    }


    var search;
    var instance = new Mark(".select2-results");

    $(".branches").select2({
        language: "cs",
        ajax: {
            url: "/api/search/",
            data: function (params) {
                search = params.term;
                return {
                    q: params.term
                };
            },
            processResults: function (data) {
                return {
                    results: $.map(data.branches, function (item) {
                        return {
                            id: item.id,
                            text: item.name,
                            city: item.city,
                            cityPart: item.city_part,
                            address: item.address,
                            zip: item.zip,
                            kind: item.kind
                        }
                    })
                };
            },
            complete: function () {
                if (search !== undefined) {
                    setTimeout(function () {
                        instance.mark(search);
                    }, 100);
                }
            }
        },
        templateResult: selectTemplate,
        templateSelection: selectTemplate
    });

    $("select").on("select2:results:all", function (e) {
        alert("adasd");
    });

    $('select').on('select2:select', function (e) {
        $("#callbackForm").find("input[name='branchId']").val($(this).val());
        $("#callbackForm").show();
    });


    $('select').on('select2:close', function (e) {
        $("#tooltip").hide(100);
    });


    $body.on("mouseover", ".tooltipTrigger", function (e) {
        $.ajax({
            url: $(this).data("url")
        })
            .done(function (data) {
                $("#tooltip").html(data).show(100);
            })
    });

    $body.on("mouseout", ".tooltipTrigger", function (e) {
        $("#tooltip").hide(100);
    });

    $body.on("mousemove", ".tooltipTrigger", function (e) {
        var obj = document.getElementById("tooltip");

        obj.style.top = e.pageY + "px";
        obj.style.left = e.pageX + 12 + "px";
    });


});