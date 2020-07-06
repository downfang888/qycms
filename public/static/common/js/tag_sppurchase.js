// 加入购物车
function shop_add_cart() {
    var JsonData    = fe912b5dac71082e12c1827a3107f9b;
    var QuantityObj = document.getElementById(JsonData.quantity);
    var SelectValueIds = document.getElementById(JsonData.SelectValueIds);
    var aid = JsonData.aid;
    var num = QuantityObj.value;
    var url = JsonData.shop_add_cart_url;
    var ajaxdata = 'aid='+aid+'&num='+num+'&spec_value_id='+SelectValueIds.value;

    // 库存数量
    var StockCountObj = document.getElementById('ey_stock_1565602291').value;
    if (parseInt(StockCountObj) == 0) {
        alert('商品已售罄！');
        return false;
    } else if (parseInt(StockCountObj) < parseInt(num)) {
        alert('商品库存不足！');
        return false;
    }

    //创建异步对象
    var ajaxObj = new XMLHttpRequest();
    ajaxObj.open("post", url, true);
    ajaxObj.setRequestHeader("X-Requested-With","XMLHttpRequest");
    ajaxObj.setRequestHeader("Content-type","application/x-www-form-urlencoded");
    //发送请求
    ajaxObj.send(ajaxdata);

    ajaxObj.onreadystatechange = function () {
        // 这步为判断服务器是否正确响应
        if (ajaxObj.readyState == 4 && ajaxObj.status == 200) {
            var json = ajaxObj.responseText;  
            var res = JSON.parse(json);
            if ('1' == res.code) {
                // 是否要去购物车 
                shop_cart_list(JsonData.shop_cart_list_url);
            }else{
                if (-1 == res.data.code) {
                    confirm(res.msg);
                }else{
                    // 去登陆
                    is_login(JsonData.login_url);
                }
            }
        } 
    };
}

// 立即购买
function BuyNow(aid){
    var JsonData    = fe912b5dac71082e12c1827a3107f9b;
    var QuantityObj = document.getElementById(JsonData.quantity);
    var SelectValueIds = document.getElementById(JsonData.SelectValueIds);

    var url = JsonData.shop_buy_now_url;
    var aid = JsonData.aid;
    var num = QuantityObj.value;
    var ajaxdata = 'aid='+aid+'&num='+num+'&spec_value_id='+SelectValueIds.value;

    // 库存数量
    var StockCountObj = document.getElementById('ey_stock_1565602291').value;
    if (parseInt(StockCountObj) == 0) {
        alert('商品已售罄！');
        return false;
    } else if (parseInt(StockCountObj) < parseInt(num)) {
        alert('商品库存不足！');
        return false;
    }

    //创建异步对象
    var ajaxObj = new XMLHttpRequest();
    ajaxObj.open("post", url, true);
    ajaxObj.setRequestHeader("X-Requested-With","XMLHttpRequest");
    ajaxObj.setRequestHeader("Content-type","application/x-www-form-urlencoded");
    //发送请求
    ajaxObj.send(ajaxdata);

    ajaxObj.onreadystatechange = function () {
        // 这步为判断服务器是否正确响应
        if (ajaxObj.readyState == 4 && ajaxObj.status == 200) {
            var json = ajaxObj.responseText;  
            var res  = JSON.parse(json);
            if ('1' == res.code) {
                // 去购买
                window.location.href = res.url;
            }else{
                if (-1 == res.data.code) {
                    confirm(res.msg);
                }else{
                    // 去登陆
                    is_login(JsonData.login_url);
                }
            }
        } 
    };
}

// 数量加减处理
function CartUnifiedAlgorithm(symbol){
    // 数量
    var QuantityObj = document.getElementById(fe912b5dac71082e12c1827a3107f9b.quantity);
    // 库存数量
    var StockCountObj = document.getElementById('ey_stock_1565602291');
    // 默认数量
    var quantity = '';
    if ('change' == symbol) {
        // 直接修改数量
        if ('1' > QuantityObj.value || '' == QuantityObj.value) {
            quantity = 1;
            alert('商品数量最少为1');
        }else{
            if (Number(QuantityObj.value) > Number(StockCountObj.value)) {
                quantity = Number(StockCountObj.value);
            }else{
                quantity = Number(QuantityObj.value);
            }
        }
    }else if ('+' == symbol) {
        // 加数量
        quantity = Number(QuantityObj.value) + 1;
    }else if ('-' == symbol && QuantityObj.value > '1') {
        // 减数量
        quantity = Number(QuantityObj.value) - 1;
    }else{
        quantity = 1;
        // 如果数量小于1则自动填充1
        alert('商品数量最少为1');
    }
    // 数量是否大于库存量
    if (StockCountObj.value < quantity) {
        alert('这件产品库存仅为：'+StockCountObj.value);
        return false;
    }
    // 加载数量
    QuantityObj.value = quantity;
}

// 去购车去
function shop_cart_list(url){
    var mymessage = confirm("加入购物车成功，前往购物车！");
    if(mymessage == true){
        window.location.href = url;
    }
}

// 去登陆
function is_login(url){
    var mymessage = confirm("您还没未登录，请登录后购买！");
    if(mymessage == true){
        window.location.href = url;
    }
}

function sortNumber(a, b) { 
    return a - b 
}

function SpecSelect(spec_mark_id, spec_value_id, discount_price) {
    var JsonData = fe912b5dac71082e12c1827a3107f9b;

    // 清除同一类下的所有选中参数class
    var ClassArray = document.getElementsByClassName("spec_mark_"+spec_mark_id);
    for (var i = 0; i < ClassArray.length; i++) {
        ClassArray[i].classList.remove("btn-danger");
    }

    // 当前点击的添加上class
    document.getElementsByClassName('spec_value_'+spec_value_id)[0].classList.add('btn-danger');

    /*规格值ID处理*/
    // 获取所有选中的规格值ID
    var SelectValueIds = '';
    var danger = document.getElementsByClassName('btn-danger');
    for (var i = 0; i < danger.length; i++) {
        if (danger[i].dataset.spec_value_id) {
            SelectValueIds += danger[i].dataset.spec_value_id;
            SelectValueIds += '_';
        }
    }
    // 去除最后一个字符
    SelectValueIds = SelectValueIds.substring(0, SelectValueIds.length-1);
    // 字符串转数组
    SelectValueIds = SelectValueIds.split('_');
    // 从小到大排序
    SelectValueIds = SelectValueIds.sort(sortNumber);
    // 数组转字符串
    SelectValueIds = SelectValueIds.join('_');
    /* END */

    // 解析json数据
    var SpecData = JSON.parse(JsonData.SpecData);
    
    // 更新价格及库存
    for(var i = 0; i < SpecData.length; i++){
        if (SelectValueIds == SpecData[i]['spec_value_id']) {
            document.getElementById('SelectValueIds').value  = SelectValueIds;
            if (discount_price) {
                var old_price   = Number(SpecData[i]['spec_price']);
                document.getElementById('old_price').innerText   = old_price.toFixed(2);
                var users_price = Number(SpecData[i]['spec_price']) * discount_price;
                document.getElementById('users_price').innerText = users_price.toFixed(2);
            }else{
                var users_price = Number(SpecData[i]['spec_price']);
                document.getElementById('users_price').innerText = users_price.toFixed(2);
            }
            document.getElementById('stock_count').innerText = SpecData[i]['spec_stock'];
            document.getElementById('ey_stock_1565602291').value = SpecData[i]['spec_stock'];
            // 更新价格和库存后，购买数量重置为 1 
            document.getElementById(fe912b5dac71082e12c1827a3107f9b.quantity).value = 1;
        }
    }
}