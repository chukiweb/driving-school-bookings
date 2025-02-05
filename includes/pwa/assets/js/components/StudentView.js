// StudentView.js
const StudentView = () => {
    const [points, setPoints] = React.useState(0);
    const [bookings, setBookings] = React.useState([]);
    
    return (
      <div>
        <div>Puntos disponibles: {points}</div>
        <BookingsList bookings={bookings} />
        <NewBookingForm points={points} setPoints={setPoints} />
      </div>
    );
   };