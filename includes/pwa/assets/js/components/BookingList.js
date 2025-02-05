 // BookingsList.js
 const BookingsList = ({ bookings }) => (
    <div>
      <h2>Próximas clases</h2>
      <table>
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Hora</th>
            {user.role === 'profesor' && <th>Estudiante</th>}
            {user.role === 'estudiante' && <th>Profesor</th>}
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          {bookings.map(booking => (
            <BookingRow key={booking.id} booking={booking} />
          ))}
        </tbody>
      </table>
    </div>
   );