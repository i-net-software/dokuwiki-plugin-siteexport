test("JavaScript did compile successfully", function(assert){
   
   ok(jQuery, "jQuery does exist");
   ok(jQuery.siteexport().throbberCount == 0, "Siteexport does exist");
    
});