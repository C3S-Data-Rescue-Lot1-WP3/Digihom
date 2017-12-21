/*
Various object
 */
// create XML HTTP Request Object by trying the different models
function oj_XHRequest(){
  var res = null;
  try{ res = new ActiveXObject("Microsoft.XMLHTTP");}
  catch(Error){
    try{ res = new ActiveXObject("MSXML2.XMLHTTP");}
    catch(Error){
      try{ res = new XMLHttpRequest();}
      catch(Error){
	alert("Not able to initalize the HTTP-Request object");
      }
    }
  }
  return res;
}

