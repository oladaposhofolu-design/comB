function saveAttendance(studentCode, status){

fetch("save_attendance.php", {

    method: "POST",

    headers:{
        "Content-Type":"application/json"
    },

    body: JSON.stringify({

        student_code: studentCode,
        status: status,
        day: today

    })

})
.then(response => response.json())
.then(data => {

    console.log(data);

});
}