function ey_1564127378() {
    var JsonData  = ey_1564127251;
    var get_url   = JsonData.get_url;
    var ClosePage = JsonData.ClosePage;

    // 步骤一:创建异步对象
    var ajax = new XMLHttpRequest();
    //步骤二:设置请求的url参数,参数一是请求的类型,参数二是请求的url,可以带参数,动态的传递参数starName到服务端
    ajax.open("post", get_url, true);
    // 给头部添加ajax信息
    ajax.setRequestHeader("X-Requested-With","XMLHttpRequest");
    // 如果需要像 HTML 表单那样 POST 数据，请使用 setRequestHeader() 来添加 HTTP 头。然后在 send() 方法中规定您希望发送的数据：
    ajax.setRequestHeader("Content-type","application/x-www-form-urlencoded");
    //步骤三:发送请求+数据
    ajax.send('_ajax=1');
    //步骤四:注册事件 onreadystatechange 状态改变就会调用
    ajax.onreadystatechange = function () {
        //步骤五 请求成功，处理逻辑
        if (ajax.readyState==4 && ajax.status==200) {
            var json = ajax.responseText;  
            var res  = JSON.parse(json);
            if (0 == res.code) {
                // 不可以查看
                document.body.innerHTML = "";
                setTimeout(function(){
                    confirm(res.msg);
                    if (ClosePage) {
                        window.close();
                    }else{
                        var return_url = document.referrer;
                        window.location.href = return_url; 
                    }
                },600);
            }
      　}
    };
}

ey_1564127378();