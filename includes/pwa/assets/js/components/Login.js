// Login.js 
const Login = ({ setUser }) => {
    const handleSubmit = async (e) => {
      e.preventDefault();
      const response = await fetch('/wp-json/dsb/v1/auth', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          username: e.target.username.value,
          password: e.target.password.value
        })
      });
      const data = await response.json();
      if (data.success) setUser(data.user);
    };
   
    return (
      <form onSubmit={handleSubmit}>
        <input name="username" type="text" required />
        <input name="password" type="password" required />
        <button type="submit">Login</button>
      </form>
    );
   };