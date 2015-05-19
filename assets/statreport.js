(function($) {
    var buildUrl = function() {
        var self = this;
        var options = self.data('statreport');

        var params = {};
        $.each(options.params, function(key, param) {
            if(param instanceof $) {
                if(param.is("input") && param.attr("type") == "checkbox") {
                    params[key] = param.filter(":checked").val();
                } else {
                    params[key] = param.val();
                }
            } else {
                params[key] = param;
            }
        });

        var query = $.param(params);
        var url = '';
        if (options.url.indexOf("?") != -1){
            url = options.url + '&' + query;
        } else {
            url = options.url + '?' + query;
        }
        return url;
    };

    var ajaxMask = function(options) {
        var settings = $.extend({
            stop: false
        }, options);

        if (!settings.stop) {
            var loadingDiv = $('<div class="ajax-mask"><div class="loading"></div></div>')
                .css({
                    'position': 'absolute',
                    'top': 0,
                    'left':0,
                    'width':'100%',
                    'height':'100%'
                });

            $(this).css({ 'position':'relative' }).append(loadingDiv);
        } else {
            $(this).find('.ajax-mask').remove();
        }
    };

    var methods = {
        init: function(options) {
            $.fn.dataTable.ext.errMode = 'none';

            var self = this;
            var defaults = {
                table: null,
                chart: null,
                url: '',
                params: {},
                chartSeries: [],
                chartOptions: {},
                dataTablesOptions: {},
                onError: null
            };

            options = $.extend(defaults, options);
            self.data('statreport', options);

            var dataTablesOptions = options.dataTablesOptions;
            dataTablesOptions.ajax = {
                'url': buildUrl.call(self),
                'dataSrc': 'table'
            };

            var dataTable = options.table.on('preXhr.dt', function(e, settings, data) {
                self.data('loading', true);
                ajaxMask.call(self);
            }).dataTable(dataTablesOptions);
            self.data('data-tables', dataTable);

            if(options.onError !== null) {
                dataTable.on('error.dt', options.onError);
            }
            dataTable.on('xhr.dt', function(e, settings, json) {
                self.data('loading', false);

                if(json != null) {
                    var data = options.chartSeries.concat(json.chart);
                    console.log(data);  // 调试用
                    var highchartsOptions = options.chartOptions;
                    highchartsOptions.data = {
                        rows: data
                    };
                    options.chart.highcharts(highchartsOptions);

                    if(typeof json.caption != 'undefined') {
                        self.find('div.statreport-caption').html(json.caption);
                    }
                }

                ajaxMask.call(self, { stop: true });
            });

            self.find('div.toggle-view-buttons > button').click(function() {
                self.statReport('view', $(this).val());
            });
            self.statReport('view', 'chart');

            return self;
        },

        view: function(type) {
            var self = this;

            self.data('view', type);

            self.find('div.toggle-view-buttons > button').removeClass('btn-primary');
            self.find('div.toggle-view-buttons > button[value="' + type  + '"]').addClass('btn-primary');

            self.find('div.statreport-view[data-view-role!="' + type + '"]').hide();
            self.find('div.statreport-view[data-view-role="' + type + '"]').show();

            if(type == 'chart') {
                $(window).trigger('resize');
            }
        },

        load: function() {
            var self = this;
            if( ! self.data('loading')) {
                self.data('data-tables').api().ajax.url(buildUrl.call(this)).load();
            }
        }
    };

    $.fn.statReport = function() {
        var method = arguments[0];

        var isDefaultMethod = false;
        if(typeof methods[method] != 'undefined') {
            method = methods[method];
        } else if(typeof method == "object" || ! method) {
            isDefaultMethod = true;
            method = methods.init;
        } else {
            $.error("Method " +  method + " does not exist on statReport");
            return this;
        }

        return method.apply(this, Array.prototype.slice.call(arguments, isDefaultMethod ? 0 : 1, arguments.length));
    };

})(jQuery);
