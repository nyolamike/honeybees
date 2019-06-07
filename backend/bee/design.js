//we need to have a hive as part of every header
var pollen = {
    visit_flower:"login",
    with:{
        username:"Jhon Doe",
        password:"qwerty"
    }
}
bee.send(pollen).get(function(honey){

}); 


//the register _flower is for seting up a hive and a master account
//_a must allways be the first attribute

/*
there should be no column or table or db name with __ (double underscore)
these are used for path in select statements

there should be no table or database whose name is both singular and plural
as in the singular and plural versions are the same word

_w is always an array of conditions of three values left,cond,right
_w:[
    [["name","=","foo"],"and",["page",">=",30]]
]





*/