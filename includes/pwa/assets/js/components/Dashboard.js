// Dashboard.js
const Dashboard = ({ user }) => {
    return (
      <div>
        <h1>Bienvenido {user.name}</h1>
        {user.role === 'profesor' ? <TeacherView /> : <StudentView />}
      </div>
    );
   };