'use strict';

(function () {

  // JS global variables from app.js file 
  const colors = window.config.colors;
  const fontFamily = window.config.fontFamily;

  const revenueChartData = [
    { date: 'Jan 01 2026', value: 49.3 },
    { date: 'Jan 02 2026', value: 48.7 },
    { date: 'Jan 03 2026', value: 50.6 },
    { date: 'Jan 04 2026', value: 53.3 },
    { date: 'Jan 05 2026', value: 54.7 },
    { date: 'Jan 06 2026', value: 53.8 },
    { date: 'Jan 07 2026', value: 54.6 },
    { date: 'Jan 08 2026', value: 56.7 },
    { date: 'Jan 09 2026', value: 56.9 },
    { date: 'Jan 10 2026', value: 56.1 },
    { date: 'Jan 11 2026', value: 56.5 },
    { date: 'Jan 12 2026', value: 60.3 },
    { date: 'Jan 13 2026', value: 58.7 },
    { date: 'Jan 14 2026', value: 61.4 },
    { date: 'Jan 15 2026', value: 61.1 },
    { date: 'Jan 16 2026', value: 58.5 },
    { date: 'Jan 17 2026', value: 54.7 },
    { date: 'Jan 18 2026', value: 52.0 },
    { date: 'Jan 19 2026', value: 51.0 },
    { date: 'Jan 20 2026', value: 47.4 },
    { date: 'Jan 21 2026', value: 48.5 },
    { date: 'Jan 22 2026', value: 48.9 },
    { date: 'Jan 23 2026', value: 53.5 },
    { date: 'Jan 24 2026', value: 50.2 },
    { date: 'Jan 25 2026', value: 46.2 },
    { date: 'Jan 26 2026', value: 48.6 },
    { date: 'Jan 27 2026', value: 51.7 },
    { date: 'Jan 28 2026', value: 51.3 },
    { date: 'Jan 29 2026', value: 50.2 },
    { date: 'Jan 30 2026', value: 54.6 },
    { date: 'Jan 31 2026', value: 52.4 },
    { date: 'Feb 01 2026', value: 53.0 },
    { date: 'Feb 02 2026', value: 57.0 },
    { date: 'Feb 03 2026', value: 52.9 },
    { date: 'Feb 04 2026', value: 48.7 },
    { date: 'Feb 05 2026', value: 52.6 },
    { date: 'Feb 06 2026', value: 53.5 },
    { date: 'Feb 07 2026', value: 58.5 },
    { date: 'Feb 08 2026', value: 55.1 },
    { date: 'Feb 09 2026', value: 58.0 },
    { date: 'Feb 10 2026', value: 61.3 },
    { date: 'Feb 11 2026', value: 57.7 },
    { date: 'Feb 12 2026', value: 60.2 },
    { date: 'Feb 13 2026', value: 61.0 },
    { date: 'Feb 14 2026', value: 57.7 },
    { date: 'Feb 15 2026', value: 56.8 },
    { date: 'Feb 16 2026', value: 58.9 },
    { date: 'Feb 17 2026', value: 62.4 },
    { date: 'Feb 18 2026', value: 58.7 },
    { date: 'Feb 19 2026', value: 58.4 },
    { date: 'Feb 20 2026', value: 56.7 },
    { date: 'Feb 21 2026', value: 52.7 },
    { date: 'Feb 22 2026', value: 52.3 },
    { date: 'Feb 23 2026', value: 50.5 },
    { date: 'Feb 24 2026', value: 55.4 },
    { date: 'Feb 25 2026', value: 50.4 },
    { date: 'Feb 26 2026', value: 52.4 },
    { date: 'Feb 27 2026', value: 48.7 },
    { date: 'Feb 28 2026', value: 47.4 },
    { date: 'Feb 29 2026', value: 46.8 },
    { date: 'Mar 01 2026', value: 43.3 },
    { date: 'Mar 02 2026', value: 38.9 },
    { date: 'Mar 03 2026', value: 34.7 },
    { date: 'Mar 04 2026', value: 31.0 },
    { date: 'Mar 05 2026', value: 32.6 },
    { date: 'Mar 06 2026', value: 36.8 },
    { date: 'Mar 07 2026', value: 35.8 },
    { date: 'Mar 08 2026', value: 32.7 },
    { date: 'Mar 09 2026', value: 33.2 },
    { date: 'Mar 10 2026', value: 30.8 },
    { date: 'Mar 11 2026', value: 28.6 },
    { date: 'Mar 12 2026', value: 28.4 },
    { date: 'Mar 13 2026', value: 27.7 },
    { date: 'Mar 14 2026', value: 27.7 },
    { date: 'Mar 15 2026', value: 25.9 },
    { date: 'Mar 16 2026', value: 24.3 },
    { date: 'Mar 17 2026', value: 21.9 },
    { date: 'Mar 18 2026', value: 22.0 },
    { date: 'Mar 19 2026', value: 23.5 },
    { date: 'Mar 20 2026', value: 27.3 },
    { date: 'Mar 21 2026', value: 30.2 },
    { date: 'Mar 22 2026', value: 27.2 },
    { date: 'Mar 23 2026', value: 29.9 },
    { date: 'Mar 24 2026', value: 25.1 },
    { date: 'Mar 25 2026', value: 23.0 },
    { date: 'Mar 26 2026', value: 23.7 },
    { date: 'Mar 27 2026', value: 23.4 },
    { date: 'Mar 28 2026', value: 27.9 },
    { date: 'Mar 29 2026', value: 23.2 },
    { date: 'Mar 30 2026', value: 23.9 },
    { date: 'Mar 31 2026', value: 19.2 },
    { date: 'Apr 01 2026', value: 15.1 },
    { date: 'Apr 02 2026', value: 15.0 },
    { date: 'Apr 03 2026', value: 11.0 },
    { date: 'Apr 04 2026', value: 9.2 },
    { date: 'Apr 05 2026', value: 7.5 },
    { date: 'Apr 06 2026', value: 11.6 },
    { date: 'Apr 07 2026', value: 15.7 },
    { date: 'Apr 08 2026', value: 13.9 },
    { date: 'Apr 09 2026', value: 12.5 },
    { date: 'Apr 10 2026', value: 13.5 },
    { date: 'Apr 11 2026', value: 15.0 },
    { date: 'Apr 12 2026', value: 13.9 },
    { date: 'Apr 13 2026', value: 13.2 },
    { date: 'Apr 14 2026', value: 18.1 },
    { date: 'Apr 15 2026', value: 20.6 },
    { date: 'Apr 16 2026', value: 21.0 },
    { date: 'Apr 17 2026', value: 25.3 },
    { date: 'Apr 18 2026', value: 25.3 },
    { date: 'Apr 19 2026', value: 20.9 },
    { date: 'Apr 20 2026', value: 18.7 },
    { date: 'Apr 21 2026', value: 15.3 },
    { date: 'Apr 22 2026', value: 14.5 },
    { date: 'Apr 23 2026', value: 17.9 },
    { date: 'Apr 24 2026', value: 15.9 },
    { date: 'Apr 25 2026', value: 16.3 },
    { date: 'Apr 26 2026', value: 14.1 },
    { date: 'Apr 27 2026', value: 12.1 },
    { date: 'Apr 28 2026', value: 14.8 },
    { date: 'Apr 29 2026', value: 17.2 },
    { date: 'Apr 30 2026', value: 17.7 },
    { date: 'May 01 2026', value: 14.0 },
    { date: 'May 02 2026', value: 18.6 },
    { date: 'May 03 2026', value: 18.4 },
    { date: 'May 04 2026', value: 22.6 },
    { date: 'May 05 2026', value: 25.0 },
    { date: 'May 06 2026', value: 28.1 },
    { date: 'May 07 2026', value: 28.0 },
    { date: 'May 08 2026', value: 24.1 },
    { date: 'May 09 2026', value: 24.2 },
    { date: 'May 10 2026', value: 28.2 },
    { date: 'May 11 2026', value: 26.2 },
    { date: 'May 12 2026', value: 29.3 },
    { date: 'May 13 2026', value: 26.0 },
    { date: 'May 14 2026', value: 23.9 },
    { date: 'May 15 2026', value: 28.8 },
    { date: 'May 16 2026', value: 25.1 },
    { date: 'May 17 2026', value: 21.7 },
    { date: 'May 18 2026', value: 23.0 },
    { date: 'May 19 2026', value: 20.7 },
    { date: 'May 20 2026', value: 29.7 },
    { date: 'May 21 2026', value: 30.2 },
    { date: 'May 22 2026', value: 32.5 },
    { date: 'May 23 2026', value: 31.4 },
    { date: 'May 24 2026', value: 33.6 },
    { date: 'May 25 2026', value: 30.0 },
    { date: 'May 26 2026', value: 34.2 },
    { date: 'May 27 2026', value: 36.9 },
    { date: 'May 28 2026', value: 35.5 },
    { date: 'May 29 2026', value: 34.7 },
    { date: 'May 30 2026', value: 36.9 },
    { date: 'May 31 2026', value: 37.2 },
  ];

  // Helper functions for backward compatibility
  const getRevenueValues = () => {
    return revenueChartData.map((item) => item.value);
  };

  const getRevenueCategories = () => {
    return revenueChartData.map((item) => item.date);
  };





  // Date Picker
  if (document.querySelector('#dashboardDate')) {
    flatpickr("#dashboardDate", {
      wrap: true,
      dateFormat: "d-M-Y",
      defaultDate: "today",
    });
  }
  // Date Picker - END





  // New Customers Chart
  const customersChartElement = document.querySelector('#customersChart');
  if (customersChartElement) {
    const customersChartOptions = {
      chart: {
        type: "line",
        height: 60,
        sparkline: {
          enabled: !0
        }
      },
      series: [{
        name: '',
        data: [3844, 3855, 3841, 3867, 3822, 3843, 3821, 3841, 3856, 3827, 3843]
      }],
      xaxis: {
        type: 'datetime',
        categories: [
          'Jan 01 2026',
          'Jan 02 2026',
          'Jan 03 2026',
          'Jan 04 2026',
          'Jan 05 2026',
          'Jan 06 2026',
          'Jan 07 2026',
          'Jan 08 2026',
          'Jan 09 2026',
          'Jan 10 2026',
          'Jan 11 2026',
        ],
      },
      yaxis: {
        min: 3820,
        max: 3870,
        tickAmount: 4,
        labels: {
          show: false
        }
      },
      stroke: {
        width: 2,
        curve: "smooth"
      },
      markers: {
        size: 0
      },
      colors: [colors.primary],
    };
    const customersChart = new ApexCharts(customersChartElement, customersChartOptions);
    customersChart.render();
  }
  // New Customers Chart - END




  // Orders Chart
  const ordersChartElement = document.querySelector('#ordersChart');
  if (ordersChartElement) {
    const ordersChartOptions = {
      chart: {
        type: "bar",
        height: 60,
        sparkline: {
          enabled: !0
        }
      },
      plotOptions: {
        bar: {
          borderRadius: 2,
          columnWidth: "60%"
        }
      },
      colors: [colors.primary],
      series: [{
        name: '',
        data: [36, 77, 52, 90, 74, 35, 55, 23, 47, 10, 63]
      }],
      xaxis: {
        type: 'datetime',
        categories: [
          'Jan 01 2026',
          'Jan 02 2026',
          'Jan 03 2026',
          'Jan 04 2026',
          'Jan 05 2026',
          'Jan 06 2026',
          'Jan 07 2026',
          'Jan 08 2026',
          'Jan 09 2026',
          'Jan 10 2026',
          'Jan 11 2026',
        ],
      },
      yaxis: {
        min: 0,
        max: 90,
        tickAmount: 4,
        labels: {
          show: false
        }
      },
    };
    const ordersChart = new ApexCharts(ordersChartElement, ordersChartOptions);
    ordersChart.render();
  }
  // Orders Chart - END




  // Growth Chart
  const growthChartElement = document.querySelector('#growthChart');
  if (growthChartElement) {
    const growthChartOptions = {
      chart: {
        type: "line",
        height: 60,
        sparkline: {
          enabled: !0
        }
      },
      series: [{
        name: '',
        data: [41, 45, 44, 46, 52, 54, 43, 74, 82, 82, 89]
      }],
      xaxis: {
        type: 'datetime',
        categories: [
          'Jan 01 2026',
          'Jan 02 2026',
          'Jan 03 2026',
          'Jan 04 2026',
          'Jan 05 2026',
          'Jan 06 2026',
          'Jan 07 2026',
          'Jan 08 2026',
          'Jan 09 2026',
          'Jan 10 2026',
          'Jan 11 2026',
        ],
      },
      yaxis: {
        min: 40,
        max: 90,
        tickAmount: 4,
        labels: {
          formatter: function (val) {
            return val + "%";
          }
        }
      },
      stroke: {
        width: 2,
        curve: "smooth"
      },
      markers: {
        size: 0
      },
      colors: [colors.primary],
    };
    const growthChart = new ApexCharts(growthChartElement, growthChartOptions);
    growthChart.render();
  }
  // Growth Chart - END




  // Revenue Chart
  const revenueChartElement = document.querySelector('#revenueChart');
  if (revenueChartElement) {
    const revenueChartOptions = {
      chart: {
        type: "line",
        height: '400',
        parentHeightOffset: 0,
        foreColor: colors.secondary,
        toolbar: {
          show: false
        },
        zoom: {
          enabled: false
        },
      },
      colors: [colors.primary, colors.danger, colors.warning],
      grid: {
        padding: {
          bottom: -4,
        },
        borderColor: colors.gridBorder,
        xaxis: {
          lines: {
            show: true
          }
        }
      },
      series: [
        {
          name: "Revenue",
          data: getRevenueValues()
        },
      ],
      xaxis: {
        type: "datetime",
        categories: getRevenueCategories(),
        lines: {
          show: true
        },
        axisBorder: {
          color: colors.gridBorder,
        },
        axisTicks: {
          color: colors.gridBorder,
        },
        crosshairs: {
          stroke: {
            color: colors.secondary,
          },
        },
      },
      yaxis: {
        min: -10,
        max: 70,
        title: {
          text: 'Revenue ( $1000 x )',
          style: {
            size: 9,
            color: colors.secondary
          }
        },
        tickAmount: 4,
        tooltip: {
          enabled: true
        },
        crosshairs: {
          stroke: {
            color: colors.secondary,
          },
        },
      },
      markers: {
        size: 0,
      },
      stroke: {
        width: 2,
        curve: "straight",
      },
    };
    const revenueChart = new ApexCharts(revenueChartElement, revenueChartOptions);
    revenueChart.render();
  }
  // Revenue Chart - END





  // Monthly Sales Chart
  const monthlySalesChartElement = document.querySelector('#monthlySalesChart');
  if (monthlySalesChartElement) {
    const monthlySalesChartOptions = {
      chart: {
        type: 'bar',
        height: '318',
        parentHeightOffset: 0,
        foreColor: colors.secondary,
        toolbar: {
          show: false
        },
        zoom: {
          enabled: false
        },
      },
      colors: [colors.primary],
      fill: {
        opacity: .9
      },
      grid: {
        padding: {
          bottom: -4
        },
        borderColor: colors.gridBorder,
        xaxis: {
          lines: {
            show: true
          }
        }
      },
      series: [{
        name: 'Sales',
        data: [152, 109, 93, 113, 126, 161, 188, 143, 102, 113, 116, 124]
      }],
      xaxis: {
        type: 'category',
        categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        axisBorder: {
          color: colors.gridBorder,
        },
        axisTicks: {
          color: colors.gridBorder,
        },
      },
      yaxis: {
        title: {
          text: 'Number of Sales',
          style: {
            size: 9,
            color: colors.secondary
          }
        },
      },
      legend: {
        show: true,
        position: "top",
        horizontalAlign: 'center',
        fontFamily: fontFamily,
        itemMargin: {
          horizontal: 8,
          vertical: 0
        },
      },
      stroke: {
        width: 0
      },
      dataLabels: {
        enabled: true,
        style: {
          fontSize: '10px',
          fontFamily: fontFamily,
        },
        offsetY: -27
      },
      plotOptions: {
        bar: {
          columnWidth: "50%",
          borderRadius: 4,
          dataLabels: {
            position: 'top',
            orientation: 'vertical',
          }
        },
      },
    }

    const monthlySalesChart = new ApexCharts(monthlySalesChartElement, monthlySalesChartOptions);
    monthlySalesChart.render();
  }
  // Monthly Sales Chart - END





  // Cloud Storage Chart
  const storageChartElement = document.querySelector('#storageChart');
  if (storageChartElement) {
    const storageChartOptions = {
      chart: {
        height: 260,
        type: "radialBar"
      },
      series: [67],
      colors: [colors.primary],
      plotOptions: {
        radialBar: {
          hollow: {
            margin: 15,
            size: "70%"
          },
          track: {
            show: true,
            background: colors.gridBorder,
            strokeWidth: '100%',
            opacity: 1,
            margin: 5,
          },
          dataLabels: {
            showOn: "always",
            name: {
              offsetY: -11,
              show: true,
              color: colors.secondary,
              fontSize: "13px"
            },
            value: {
              color: colors.secondary,
              fontSize: "30px",
              show: true
            }
          }
        }
      },
      fill: {
        opacity: 1
      },
      stroke: {
        lineCap: "round",
      },
      labels: ["Storage Used"]
    };

    const storageChart = new ApexCharts(storageChartElement, storageChartOptions);
    storageChart.render();
  }
  // Cloud Storage Chart - END


})();