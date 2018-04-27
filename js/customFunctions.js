function clearOutput() {
  document.getElementById("output").innerHTML = "";
}

function addItem() {
  var name = document.getElementById("itemsName");
  var quantity = document.getElementById("itemsQuantity");
  var ean = document.getElementById("itemsEan");
  var price = document.getElementById("itemsPrice");
  //to implement: counter
  var itemsNum=document.getElementById("shoppingCartNumberOfItems");
  if (name.value !== "" && quantity.value !== "" && price.length !== "") {
    var itemH6 = document.createElement("h6");
    itemH6.setAttribute("class","my-0");
    itemH6.appendChild(document.createTextNode(quantity.value +" "+ name.value));
    var itemSmall = document.createElement("small");
    itemSmall.setAttribute("class","text-muted");
    itemSmall.appendChild(document.createTextNode(ean.value));
    var itemSpan = document.createElement("span");
    itemSpan.setAttribute("class","text-muted");
    itemSpan.appendChild(document.createTextNode(price.value +"€"));
    var itemDiv = document.createElement("div");
    itemDiv.appendChild(itemH6);
    itemDiv.appendChild(itemSmall);
    var itemLi = document.createElement("li");
    itemLi.setAttribute("class","list-group-item d-flex justify-content-between 1h-condensed");
    itemLi.appendChild(itemDiv);
    itemLi.appendChild(itemSpan);
    document.getElementById("shoppingCartItems").appendChild(itemLi);
  }
  quantity.value = "";
  name.value = "";
  ean.value = "";
  price.value = "";
}