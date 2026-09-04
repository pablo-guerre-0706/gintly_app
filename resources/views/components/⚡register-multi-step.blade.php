namespace App\Http\Livewire;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

class RegistrationForm extends Component
{
    public $step = 1;
    public $userId; // Para almacenar el ID del usuario

    // Propiedades para los formularios
    public $name, $email;
    public $password, $password_confirmation;
    public $address, $city;
    public $phone, $birthdate;
    public $occupation, $company;
    public $bio;

    // Reglas de validación dinámicas (el paso 7 queda vacío porque es solo bienvenida)
    protected function rules()
    {
        return [
            1 => ['name' => 'required|string|max:255', 'email' => 'required|email|unique:users,email,' . $this->userId],
            2 => ['password' => 'required|min:8|same:password_confirmation'],
            3 => ['address' => 'required|string|max:255', 'city' => 'required|string|max:100'],
            4 => ['phone' => 'required|string|max:20', 'birthdate' => 'required|date'],
            5 => ['occupation' => 'required|string|max:100', 'company' => 'nullable|string|max:100'],
            6 => ['bio' => 'nullable|string|max:500'],
            7 => [], // Paso de bienvenida, sin reglas de validación
        ][$this->step];
    }

    public function nextStep()
    {
        // Validamos el paso actual solo si tiene reglas definidas
        $rules = $this->rules();
        if (!empty($rules)) {
            $this->validate($rules);
        }

        // SI ESTAMOS EN EL PASO 6, GUARDAMOS TODO EN LA BD Y PASamos AL 7 (Bienvenida)
        if ($this->step === 6) {
            $this->saveOrUpdateUser();
        }

        if ($this->step < 7) {
            $this->step++;
        }
    }

    public function previousStep()
    {
        // Opcional: Evitamos retroceder si ya está en la pantalla de bienvenida (paso 7)
        if ($this->step > 1 && $this->step < 7) {
            $this->step--;
        }
    }

    // Método que consolida y guarda toda la información en la base de datos
    public function saveOrUpdateUser()
    {
        $data = [
            'name' => $this->name,
            'email' => $this->email,
            'address' => $this->address,
            'city' => $this->city,
            'phone' => $this->phone,
            'birthdate' => $this->birthdate,
            'occupation' => $this->occupation,
            'company' => $this->company,
            'bio' => $this->bio,
        ];

        if (!empty($this->password)) {
            $data['password'] = Hash::make($this->password);
        }

        $user = User::updateOrCreate(
            ['id' => $this->userId], 
            $data
        );

        $this->userId = $user->id;
    }

    // Este método se ejecuta en el botón final de la pantalla de bienvenida (Paso 7)
    public function finish()
    {
        session()->flash('success', '¡Registro completado con éxito! Ya puedes iniciar sesión.');
        return redirect()->route('login');
    }

    public function render()
    {
        return view('livewire.registration-form');
    }
}