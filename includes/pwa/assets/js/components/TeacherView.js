// TeacherView.js
const TeacherView = () => {
    const [students, setStudents] = React.useState([]);
    const [bookings, setBookings] = React.useState([]);
   
    React.useEffect(() => {
      fetchStudents();
      fetchBookings(); 
    }, []);
   
    return (
      <div>
        <StudentsList students={students} />
        <BookingsList bookings={bookings} />
      </div>
    );
   };
   
   
   
  