<script src="{{ url('/api/vendor/apexcharts.min.js') }}"></script>
<script>
(function () {
    function formatReportNumber(value) {
        var number = Number(value);
        if (!Number.isFinite(number)) {
            return String(value);
        }
        if (Math.abs(number - Math.round(number)) < 0.001) {
            return Math.round(number).toLocaleString('ru-RU');
        }
        return number.toLocaleString('ru-RU', { maximumFractionDigits: 1 });
    }

    function applyCustomMeta(options) {
        var meta = options.tooltip && options.tooltip.customMeta;
        if (!meta) {
            return;
        }

        if (options.chart.type === 'donut' && meta.totalText) {
            var totalText = meta.totalText;
            options.plotOptions = options.plotOptions || {};
            options.plotOptions.pie = options.plotOptions.pie || {};
            options.plotOptions.pie.donut = options.plotOptions.pie.donut || {};
            options.plotOptions.pie.donut.labels = options.plotOptions.pie.donut.labels || {};
            options.plotOptions.pie.donut.labels.total = options.plotOptions.pie.donut.labels.total || {};
            options.plotOptions.pie.donut.labels.total.formatter = function () {
                return totalText;
            };
        }

        if (Array.isArray(meta)) {
            options.tooltip.y = options.tooltip.y || {};
            options.tooltip.y.formatter = function (value, opts) {
                var index = opts.dataPointIndex;
                return meta[index] !== undefined ? meta[index] : formatReportNumber(value);
            };
        }

        if (meta.suffixes && Array.isArray(meta.suffixes)) {
            options.tooltip.y = options.tooltip.y || {};
            options.tooltip.y.formatter = function (value, opts) {
                var suffix = meta.suffixes[opts.dataPointIndex] || '';
                return formatReportNumber(value) + suffix;
            };
        }

        if (meta.suffix !== undefined) {
            options.tooltip.y = options.tooltip.y || {};
            options.tooltip.y.formatter = function (value) {
                return formatReportNumber(value) + meta.suffix;
            };
        }

        delete options.tooltip.customMeta;
    }

    function renderCharts() {
        if (typeof ApexCharts === 'undefined') {
            console.error('ApexCharts library failed to load');
            return;
        }

        document.querySelectorAll('.apex-chart[data-config]').forEach(function (element) {
            if (element.dataset.rendered === '1') {
                return;
            }

            var raw = element.getAttribute('data-config');
            if (!raw) {
                return;
            }

            try {
                var options = JSON.parse(raw);
                applyCustomMeta(options);
                element.dataset.rendered = '1';
                new ApexCharts(element, options).render();
            } catch (error) {
                console.error('Failed to render report chart', error);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', renderCharts);
    } else {
        renderCharts();
    }
})();
</script>
