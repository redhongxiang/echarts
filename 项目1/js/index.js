$(function() {
  /**
   *  数据加载
   */

  // 模拟数据
  var data = {
  	"table": [
  		{
  			"id": "01",
  			"name": "广告位1",
  			"code": "0xa01a",
  			"version": "1.0.0",
  			"path1": "c://dingwei",
  			"status": "0",
  			"path2": "c://ziyaun",
  			"start_time": "2017.06.08",
  			"end_time": "2017.06.09",
  			"show_time": "2017.06.08" 
  		},{
  			"id": "02",
  			"name": "广告位2",
  			"code": "0xa01a",
  			"version": "1.0.0",
  			"path1": "c://dingwei",
  			"status": "0",
  			"path2": "c://ziyaun",
  			"start_time": "2017.06.08",
  			"end_time": "2017.06.09",
  			"show_time": "2017.06.08" 
  		},{
  			"id": "03",
  			"name": "广告位3",
  			"code": "0xa01a",
  			"version": "1.0.0",
  			"path1": "c://dingwei",
  			"status": "1",
  			"path2": "c://ziyaun",
  			"start_time": "2017.06.08",
  			"end_time": "2017.06.09",
  			"show_time": "2017.06.08" 
  		}
  	]
  };

  //数据加载
  var $table = $('#table');
  var tableData = data.table;
  for(var i = 0; i < tableData.length; i++) {
  	if(typeof tableData[i] === "object") {
  		$table.children('tbody').append("<tr>\
		                  <td>" + tableData[i].id + "</td>\
		                  <td>" + tableData[i].name + "</td>\
		                  <td>" + tableData[i].code + "</td>\
		                  <td>" + tableData[i].version + "</td>\
		                  <td>" + tableData[i].path1 + "</td>\
		                  <td>" + tableData[i].status + "</td>\
		                  <td>" + tableData[i].path2 + "</td>\
		                  <td>" + tableData[i].start_time + "</td>\
		                  <td>" + tableData[i].end_time + "</td>\
		                  <td>" + tableData[i].show_time + "</td>\
                		</tr>");
  	}
  }

  /**
   *  全局处理
   */
  // 查看.main元素是否满屏，如果没有则增加padding
  var $main = $('.main');
  var innerHeight = $(document).height();
  var mainHeight = $main.height()
  console.log("窗口高度：" + innerHeight);
  console.log("内容高度：" + mainHeight);
  if(mainHeight < innerHeight) {
    $main.css({"padding-bottom":innerHeight - mainHeight});
  }

  //隔行变色
  $table.children('tbody').children('tr:even').addClass('odd');
});