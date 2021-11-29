function checkEnter(e){
  var characterCode

  if(e && e.which){
    e = e
    characterCode = e.which
  }
  else {
    e = event
    characterCode = e.keyCode
  }

  if(characterCode == 13){
    document.forms[0].submit()
    return false
  }
  else {
    return true
  }
}
