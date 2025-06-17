<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>coachtech Attendance Management</title>
  <link rel="stylesheet" href="https://unpkg.com/ress/dist/ress.min.css" />
  <link rel="stylesheet" href="{{ asset('css/common.css')}}">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
  @yield('css')
</head>

<body>
  <div class="app">
    <header class="header">
      <div class="header__logo">
        <img src="{{ asset('logo.svg') }}" alt="logo" class="header__logo__image">
      </div>
      @yield('search')
      @yield('link')
    </header>
    <div class="content">
      @yield('content')
    </div>
  </div>
  @yield('js')
</body>

</html>