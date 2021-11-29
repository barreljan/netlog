function checkEnter(e){
    let characterCode
    characterCode = e.keyCode

    if(characterCode === 13){
        document.forms['settings'].submit();
        return false
    }
    else {
        return true
    }
}

