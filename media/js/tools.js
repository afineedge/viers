 $(document).ready(function(){
    $('[data-provide="datepicker"]').datepicker({
        format: {
            toValue: function (date, format, language) {
                var d = new Date(date);
                d.setDate(d.getDate() - 7);
                return d.toISOString();
            },
            toDisplay: function (date, format, language) {
                var d = new Date(date);
                d.setDate(d.getDate() + 1);
                var dd = d.getDate();
                var mm = d.getMonth()+1; //January is 0!

                var yyyy = d.getFullYear();
                if(dd<10){
                    dd='0'+dd;
                } 
                if(mm<10){
                    mm='0'+mm;
                } 
                var today = mm+'/'+dd+'/'+yyyy;
                return today;
            }
        }
    });
 })